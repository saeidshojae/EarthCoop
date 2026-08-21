<?php

namespace App\Services\Elections;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Enums\Elections\ElectionPosition;
use App\Models\Election;
use App\Models\ElectionEligibilitySnapshot;
use App\Models\ElectionTallyResult;
use App\Models\Vote;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ElectionTallyService
{
    public const DRAW_SEED_VERSION = 'stop-cycle-snapshot-sha256-v1';
    public const TIE_BREAK_VERSION = 'verifiable-draw-sha256-v1';

    public function __construct(
        private readonly ElectionLifecycleService $lifecycle,
        private readonly ElectionPolicyResolver $policyResolver,
    ) {
    }

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

            $stoppedAt = $this->resolveStoppedAt($locked);
            $cycleIdentifier = 'election:'.$locked->id;
            $snapshotHash = $this->voteSnapshotHash($locked->id);
            $drawSeed = hash('sha256', implode('|', [
                self::DRAW_SEED_VERSION,
                $this->canonicalTime($stoppedAt),
                $cycleIdentifier,
                $snapshotHash,
            ]));

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
                    ->map(function (int $candidateUserId) use ($voteCounts, $position, $drawSeed): array {
                        return [
                            'candidate_user_id' => $candidateUserId,
                            'vote_count' => (int) ($voteCounts[$candidateUserId] ?? 0),
                            'tie_break_key' => $this->tieBreakKey($drawSeed, $position, $candidateUserId),
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
                        'cycle_identifier' => $cycleIdentifier,
                        'stopped_at' => $stoppedAt,
                        'vote_snapshot_hash' => $snapshotHash,
                        'draw_seed_version' => self::DRAW_SEED_VERSION,
                        'draw_seed' => $drawSeed,
                        'tie_break_version' => self::TIE_BREAK_VERSION,
                        'tie_break_key' => $row['tie_break_key'],
                    ]);
                }
            }

            $existing = ElectionTallyResult::query()
                ->where('election_id', $locked->id)
                ->get();

            if ($existing->isNotEmpty()) {
                $expected = $allRows
                    ->sortBy(fn (array $row) => $row['position'].'|'.str_pad((string) $row['rank'], 10, '0', STR_PAD_LEFT))
                    ->map(fn (array $row) => $this->comparableRow($row))
                    ->values()
                    ->all();
                $actual = $existing
                    ->sortBy(fn (ElectionTallyResult $row) => $row->position.'|'.str_pad((string) $row->rank, 10, '0', STR_PAD_LEFT))
                    ->map(fn (ElectionTallyResult $row) => $this->comparableRow($row->toArray()))
                    ->values()
                    ->all();

                if ($expected !== $actual) {
                    throw new RuntimeException('Stored tally snapshot differs from recomputed deterministic result.');
                }

                return $existing->sortBy(fn (ElectionTallyResult $row) => $row->position.'|'.str_pad((string) $row->rank, 10, '0', STR_PAD_LEFT))->values();
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

    public function tieBreakKey(string $drawSeed, ElectionPosition $position, int $candidateUserId): string
    {
        return hash('sha256', implode('|', [
            self::TIE_BREAK_VERSION,
            $drawSeed,
            $position->value,
            $candidateUserId,
        ]));
    }

    private function resolveStoppedAt(Election $election): CarbonInterface
    {
        $transition = $election->lifecycleTransitions()
            ->where('to_status', ElectionLifecycleStatus::Closed->value)
            ->orderBy('transitioned_at')
            ->first();

        if ($transition?->transitioned_at !== null) {
            return $transition->transitioned_at;
        }

        if ($election->ends_at !== null) {
            return $election->ends_at;
        }

        throw new RuntimeException('Election stop time is not provable; tally is fail-closed.');
    }

    private function voteSnapshotHash(int $electionId): string
    {
        $canonical = Vote::query()
            ->where('election_id', $electionId)
            ->orderBy('voter_id')
            ->orderBy('candidate_user_id')
            ->orderBy('position')
            ->orderBy('id')
            ->get(['voter_id', 'candidate_user_id', 'position'])
            ->map(fn (Vote $vote) => implode(':', [
                (int) $vote->voter_id,
                (int) $vote->candidate_user_id,
                (string) $vote->position,
            ]))
            ->implode('|');

        return hash('sha256', $canonical);
    }

    private function canonicalTime(CarbonInterface $time): string
    {
        return $time->copy()->utc()->format('Y-m-d\\TH:i:s.u\\Z');
    }

    private function comparableRow(array $row): array
    {
        $stoppedAt = $row['stopped_at'];
        if (! $stoppedAt instanceof CarbonInterface) {
            $stoppedAt = \Carbon\Carbon::parse($stoppedAt);
        }

        return [
            'candidate_user_id' => (int) $row['candidate_user_id'],
            'position' => (string) $row['position'],
            'vote_count' => (int) $row['vote_count'],
            'rank' => (int) $row['rank'],
            'within_seat_cutoff' => (bool) $row['within_seat_cutoff'],
            'cycle_identifier' => (string) $row['cycle_identifier'],
            'stopped_at' => $this->canonicalTime($stoppedAt),
            'vote_snapshot_hash' => (string) $row['vote_snapshot_hash'],
            'draw_seed_version' => (string) $row['draw_seed_version'],
            'draw_seed' => (string) $row['draw_seed'],
            'tie_break_version' => (string) $row['tie_break_version'],
            'tie_break_key' => (string) $row['tie_break_key'],
        ];
    }
}
