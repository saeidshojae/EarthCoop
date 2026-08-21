<?php

namespace App\Services\Elections;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Enums\Elections\ElectionPosition;
use App\Models\Election;
use App\Models\ElectionEligibilitySnapshot;
use App\Models\ElectionTallyResult;
use App\Models\Vote;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ElectionTallyService
{
    public const TIE_BREAK_VERSION = 'sha256-election-position-candidate-v1';

    public function __construct(
        private readonly ElectionLifecycleService $lifecycle,
        private readonly ElectionPolicyResolver $policyResolver,
    ) {
    }

    /**
     * Produce an immutable, reproducible ranking snapshot for each position.
     * Equal vote counts are resolved by a public deterministic SHA-256 key based
     * only on the version, election id, position and candidate user id.
     */
    public function tally(Election $election): Collection
    {
        $status = $this->lifecycle->currentStatus($election);
        if ($status === ElectionLifecycleStatus::Closed) {
            $election = $this->lifecycle->transition(
                $election,
                ElectionLifecycleStatus::Tallying,
                'deterministic_tally_started',
                'election_tally_service',
            );
        } elseif ($status !== ElectionLifecycleStatus::Tallying) {
            throw new RuntimeException("Election [{$election->id}] is not ready for tallying.");
        }

        return DB::transaction(function () use ($election): Collection {
            $locked = Election::query()->lockForUpdate()->findOrFail($election->id);

            if (Vote::query()
                ->where('election_id', $locked->id)
                ->whereNull('candidate_user_id')
                ->exists()) {
                throw new RuntimeException('Election contains unresolved legacy vote identity; tally is fail-closed.');
            }

            $selectableUserIds = ElectionEligibilitySnapshot::query()
                ->where('election_id', $locked->id)
                ->where('selectable_eligible', true)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $policy = $this->policyResolver->resolveForGroup($locked->group);
            $allRows = collect();

            foreach ([ElectionPosition::Manager, ElectionPosition::Inspector] as $position) {
                $seatCount = $position === ElectionPosition::Manager
                    ? $this->policyResolver->managerSeatCount($policy)
                    : $this->policyResolver->inspectorSeatCount($policy);

                $voteCounts = Vote::query()
                    ->select('candidate_user_id', DB::raw('COUNT(*) as aggregate'))
                    ->where('election_id', $locked->id)
                    ->where('position', $position->legacyVotePosition())
                    ->whereNotNull('candidate_user_id')
                    ->groupBy('candidate_user_id')
                    ->pluck('aggregate', 'candidate_user_id');

                $ranked = $selectableUserIds
                    ->map(function (int $candidateUserId) use ($voteCounts, $locked, $position): array {
                        return [
                            'candidate_user_id' => $candidateUserId,
                            'vote_count' => (int) ($voteCounts[$candidateUserId] ?? 0),
                            'tie_break_key' => $this->tieBreakKey($locked->id, $position, $candidateUserId),
                        ];
                    })
                    ->sort(function (array $a, array $b): int {
                        $voteComparison = $b['vote_count'] <=> $a['vote_count'];
                        if ($voteComparison !== 0) {
                            return $voteComparison;
                        }

                        $keyComparison = strcmp($a['tie_break_key'], $b['tie_break_key']);
                        if ($keyComparison !== 0) {
                            return $keyComparison;
                        }

                        return $a['candidate_user_id'] <=> $b['candidate_user_id'];
                    })
                    ->values();

                foreach ($ranked as $index => $row) {
                    $allRows->push([
                        'election_id' => (int) $locked->id,
                        'candidate_user_id' => $row['candidate_user_id'],
                        'position' => $position->value,
                        'vote_count' => $row['vote_count'],
                        'rank' => $index + 1,
                        'within_seat_cutoff' => ($index + 1) <= $seatCount,
                        'tie_break_version' => self::TIE_BREAK_VERSION,
                        'tie_break_key' => $row['tie_break_key'],
                    ]);
                }
            }

            $existing = ElectionTallyResult::query()
                ->where('election_id', $locked->id)
                ->orderBy('position')
                ->orderBy('rank')
                ->get();

            if ($existing->isNotEmpty()) {
                $expected = $allRows->map(fn (array $row) => $this->comparableRow($row))->values()->all();
                $actual = $existing->map(fn (ElectionTallyResult $row) => $this->comparableRow($row->toArray()))->values()->all();

                if ($expected !== $actual) {
                    throw new RuntimeException('Stored tally snapshot differs from recomputed deterministic result.');
                }

                return $existing;
            }

            $talliedAt = now();
            foreach ($allRows as $row) {
                ElectionTallyResult::create($row + ['tallied_at' => $talliedAt]);
            }

            return ElectionTallyResult::query()
                ->where('election_id', $locked->id)
                ->orderBy('position')
                ->orderBy('rank')
                ->get();
        }, 3);
    }

    public function tieBreakKey(int $electionId, ElectionPosition $position, int $candidateUserId): string
    {
        return hash('sha256', implode('|', [
            self::TIE_BREAK_VERSION,
            $electionId,
            $position->value,
            $candidateUserId,
        ]));
    }

    private function comparableRow(array $row): array
    {
        return [
            'candidate_user_id' => (int) $row['candidate_user_id'],
            'position' => (string) $row['position'],
            'vote_count' => (int) $row['vote_count'],
            'rank' => (int) $row['rank'],
            'within_seat_cutoff' => (bool) $row['within_seat_cutoff'],
            'tie_break_version' => (string) $row['tie_break_version'],
            'tie_break_key' => (string) $row['tie_break_key'],
        ];
    }
}
