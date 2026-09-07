<?php

namespace App\Services\Elections;

use App\Enums\Elections\ElectionPosition;
use App\Models\Election;
use App\Models\ElectionAppointment;
use App\Models\ElectionEligibilitySnapshot;
use App\Models\ElectionProcessReview;
use App\Models\ElectionProcessReviewEndorsement;
use App\Models\ElectionReviewAuditAccess;
use App\Models\ElectionTallyResult;
use App\Models\ElectionVoteSnapshotEntry;
use App\Models\ElectionVoteSnapshotRun;
use App\Models\GroupUser;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ElectionProcessReviewService
{
    public function __construct(
        private readonly ElectionPolicyResolver $policyResolver,
        private readonly ElectionTallyService $tallyService,
    ) {}

    public function openAutomaticReview(
        Election $election,
        User $requester,
        string $ground,
        string $challengedEvent,
        CarbonInterface $eventOccurredAt,
        ?int $challengedEventId = null,
        ?int $subjectUserId = null,
        ?int $appointmentId = null,
        ?string $statement = null,
    ): ElectionProcessReview {
        if (! in_array($ground, ElectionProcessReview::GROUNDS, true)) {
            throw new InvalidArgumentException('Unsupported election review ground.');
        }
        $this->assertActiveGroupMember($election, $requester);
        if ($eventOccurredAt->isFuture()) {
            throw new InvalidArgumentException('Challenged event cannot be in the future.');
        }
        if ($appointmentId !== null) {
            $appointment = ElectionAppointment::query()->findOrFail($appointmentId);
            if ((int) $appointment->election_id !== (int) $election->id) {
                throw new InvalidArgumentException('Appointment does not belong to the challenged election.');
            }
        }

        $automatic = $this->verifyFromImmutableEvidence($election, $ground, $subjectUserId);

        return ElectionProcessReview::create([
            'election_id' => $election->id,
            'requester_user_id' => $requester->id,
            'subject_user_id' => $subjectUserId,
            'appointment_id' => $appointmentId,
            'ground' => $ground,
            'challenged_event' => trim($challengedEvent),
            'challenged_event_id' => $challengedEventId,
            'event_occurred_at' => $eventOccurredAt,
            'statement' => $statement !== null ? trim($statement) : null,
            'automatic_status' => $automatic['status'],
            'automatic_result' => $automatic['result'],
            'human_status' => 'not_requested',
            'support_count' => 0,
            'human_deadline_at' => $eventOccurredAt->copy()->addDays(7),
        ]);
    }

    public function requestHumanReview(ElectionProcessReview $review, User $requester): ElectionProcessReview
    {
        $expired = false;

        $result = DB::transaction(function () use ($review, $requester, &$expired): ElectionProcessReview {
            $locked = ElectionProcessReview::query()->lockForUpdate()->findOrFail($review->id);
            $this->assertActiveGroupMember($locked->election, $requester);
            if (now()->gt($locked->human_deadline_at)) {
                $locked->forceFill(['human_status' => 'expired'])->save();
                $expired = true;
                return $locked->refresh();
            }
            if ($locked->automatic_status === 'verified') {
                throw new RuntimeException('Automatic verification found no unresolved measurable discrepancy.');
            }
            if (! in_array($locked->human_status, ['not_requested', 'awaiting_support'], true)) {
                return $locked;
            }

            if ($locked->subject_user_id !== null && (int) $locked->subject_user_id === (int) $requester->id) {
                return $this->activateHumanReview($locked);
            }

            $locked->forceFill(['human_status' => 'awaiting_support'])->save();
            $this->endorseLocked($locked, $requester);
            return $locked->refresh();
        }, 3);

        if ($expired) {
            throw new RuntimeException('The seven-day human review request window has expired.');
        }

        return $result;
    }

    public function endorse(ElectionProcessReview $review, User $member): ElectionProcessReview
    {
        $expired = false;

        $result = DB::transaction(function () use ($review, $member, &$expired): ElectionProcessReview {
            $locked = ElectionProcessReview::query()->lockForUpdate()->findOrFail($review->id);
            $this->assertActiveGroupMember($locked->election, $member);
            if ($locked->human_status !== 'awaiting_support') {
                throw new RuntimeException('Review is not awaiting member support.');
            }
            if (now()->gt($locked->human_deadline_at)) {
                $locked->forceFill(['human_status' => 'expired'])->save();
                $expired = true;
                return $locked->refresh();
            }
            $this->endorseLocked($locked, $member);
            return $locked->refresh();
        }, 3);

        if ($expired) {
            throw new RuntimeException('The seven-day human review request window has expired.');
        }

        return $result;
    }

    public function setInterimStay(ElectionProcessReview $review, User $authority, string $reason): ElectionProcessReview
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Interim stay reason is required.');
        }
        return DB::transaction(function () use ($review, $authority, $reason): ElectionProcessReview {
            $locked = ElectionProcessReview::query()->lockForUpdate()->findOrFail($review->id);
            if ($locked->human_status !== 'pending') {
                throw new RuntimeException('Only a pending human review can be stayed.');
            }
            $locked->forceFill(['interim_state' => 'stayed', 'interim_reason' => trim($reason)])->save();
            if ($locked->appointment_id !== null) {
                ElectionAppointment::query()->whereKey($locked->appointment_id)->update(['review_state' => 'stayed']);
            }
            $this->recordAuditAccess($locked, $authority, 'review_authority', 'interim_stay_decision', ['appointment_id' => $locked->appointment_id]);
            return $locked->refresh();
        }, 3);
    }

    public function decide(
        ElectionProcessReview $review,
        User $authority,
        string $decision,
        string $reason,
        ?string $remediationReference = null,
    ): ElectionProcessReview {
        if (! in_array($decision, ['upheld', 'corrected', 'dismissed'], true)) {
            throw new InvalidArgumentException('Unsupported review decision.');
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Reasoned review decision is required.');
        }
        if ($decision === 'corrected' && trim((string) $remediationReference) === '') {
            throw new InvalidArgumentException('Corrected decisions require a remediation reference.');
        }

        return DB::transaction(function () use ($review, $authority, $decision, $reason, $remediationReference): ElectionProcessReview {
            $locked = ElectionProcessReview::query()->lockForUpdate()->findOrFail($review->id);
            if ($locked->human_status !== 'pending') {
                throw new RuntimeException('Review is not pending a human decision.');
            }
            $locked->forceFill([
                'human_status' => 'decided',
                'decided_at' => now(),
                'decided_by' => $authority->id,
                'decision' => $decision,
                'decision_reason' => trim($reason),
                'remediation_reference' => $remediationReference !== null ? trim($remediationReference) : null,
            ])->save();
            if ($locked->appointment_id !== null) {
                ElectionAppointment::query()->whereKey($locked->appointment_id)->update(['review_state' => 'clear']);
            }
            $this->recordAuditAccess($locked, $authority, 'review_authority', 'reasoned_final_decision', ['decision' => $decision]);
            return $locked->refresh();
        }, 3);
    }

    public function recordAuditAccess(ElectionProcessReview $review, User $authority, string $authorityPath, string $purpose, array $scope = []): ElectionReviewAuditAccess
    {
        if (trim($authorityPath) === '' || trim($purpose) === '') {
            throw new InvalidArgumentException('Protected audit access requires an authority path and purpose.');
        }
        return ElectionReviewAuditAccess::create([
            'review_id' => $review->id,
            'actor_user_id' => $authority->id,
            'authority_path' => trim($authorityPath),
            'purpose' => trim($purpose),
            'scope' => $scope,
            'accessed_at' => now(),
        ]);
    }

    private function endorseLocked(ElectionProcessReview $review, User $member): void
    {
        ElectionProcessReviewEndorsement::query()->firstOrCreate(
            ['review_id' => $review->id, 'user_id' => $member->id],
            ['endorsed_at' => now()],
        );
        $count = ElectionProcessReviewEndorsement::query()->where('review_id', $review->id)->count();
        $review->forceFill(['support_count' => $count])->save();
        if ($count >= 3) {
            $this->activateHumanReview($review);
        }
    }

    private function activateHumanReview(ElectionProcessReview $review): ElectionProcessReview
    {
        $review->forceFill([
            'human_status' => 'pending',
            'human_requested_at' => now(),
            'decision_due_at' => now()->addDays(14),
        ])->save();
        if ($review->appointment_id !== null) {
            ElectionAppointment::query()->whereKey($review->appointment_id)->update(['review_state' => 'provisional']);
        }
        return $review->refresh();
    }

    private function assertActiveGroupMember(Election $election, User $user): void
    {
        $active = GroupUser::query()
            ->where('group_id', $election->group_id)
            ->where('user_id', $user->id)
            ->where('status', 1)
            ->where('role', '!=', 4)
            ->exists();
        if (! $active || (bool) $user->is_system) {
            throw new RuntimeException('Only an active group member may use the election review process.');
        }
    }

    private function verifyFromImmutableEvidence(Election $election, string $ground, ?int $subjectUserId): array
    {
        $run = ElectionVoteSnapshotRun::query()
            ->where('election_id', $election->id)
            ->where('snapshot_version', 1)
            ->first();
        if ($run === null) {
            return ['status' => 'discrepancy', 'result' => ['snapshot_present' => false, 'reason' => 'missing_immutable_snapshot']];
        }

        $entries = ElectionVoteSnapshotEntry::query()
            ->where('snapshot_run_id', $run->id)
            ->orderBy('voter_id')->orderBy('candidate_user_id')->orderBy('position')->orderBy('id')
            ->get();
        $canonical = $entries->map(fn ($entry) => implode(':', [
            (int) $entry->voter_id,
            (int) $entry->candidate_user_id,
            (string) $entry->position,
        ]))->implode('|');
        $recomputedHash = hash('sha256', $canonical);
        $snapshotIntegrity = hash_equals((string) $run->snapshot_hash, $recomputedHash)
            && (int) $run->vote_count === $entries->count();

        $drawSeed = hash('sha256', implode('|', [
            ElectionTallyService::DRAW_SEED_VERSION,
            $run->stopped_at->copy()->utc()->format('Y-m-d\\TH:i:s.u\\Z'),
            $run->cycle_identifier,
            $run->snapshot_hash,
        ]));
        $selectable = ElectionEligibilitySnapshot::query()
            ->where('election_id', $election->id)
            ->where('selectable_eligible', true)
            ->pluck('user_id')->map(fn ($id) => (int) $id)->unique()->values();

        // Reviews are retrospective. Recompute with the exact policy frozen
        // into the challenged cycle, never a later admin setting. Legacy cycles
        // without a version keep the old compatibility path.
        try {
            $policy = $this->policyResolver->resolveForElection($election);
        } catch (RuntimeException) {
            $policy = $this->policyResolver->resolveForGroup($election->group);
        }
        $expected = collect();

        foreach ([ElectionPosition::Manager, ElectionPosition::Inspector] as $position) {
            $seatCount = $position === ElectionPosition::Manager
                ? $this->policyResolver->managerSeatCount($policy)
                : $this->policyResolver->inspectorSeatCount($policy);
            $counts = $entries->where('position', $position->legacyVotePosition())
                ->groupBy('candidate_user_id')->map->count();
            $ranked = $selectable->map(fn (int $candidateUserId) => [
                'candidate_user_id' => $candidateUserId,
                'position' => $position->value,
                'vote_count' => (int) ($counts[$candidateUserId] ?? 0),
                'tie_break_key' => $this->tallyService->tieBreakKey($drawSeed, $position, $candidateUserId),
            ])->sort(function (array $a, array $b): int {
                $voteComparison = $b['vote_count'] <=> $a['vote_count'];
                if ($voteComparison !== 0) return $voteComparison;
                $keyComparison = strcmp($a['tie_break_key'], $b['tie_break_key']);
                return $keyComparison !== 0 ? $keyComparison : ($a['candidate_user_id'] <=> $b['candidate_user_id']);
            })->values();
            foreach ($ranked as $index => $row) {
                $expected->push($row + ['rank' => $index + 1, 'within_seat_cutoff' => ($index + 1) <= $seatCount]);
            }
        }

        $actual = ElectionTallyResult::query()->where('election_id', $election->id)
            ->orderBy('position')->orderBy('rank')->get()
            ->map(fn (ElectionTallyResult $row) => [
                'candidate_user_id' => (int) $row->candidate_user_id,
                'position' => (string) $row->position,
                'vote_count' => (int) $row->vote_count,
                'tie_break_key' => (string) $row->tie_break_key,
                'rank' => (int) $row->rank,
                'within_seat_cutoff' => (bool) $row->within_seat_cutoff,
            ])->values();
        $expected = $expected->sortBy(fn (array $row) => $row['position'].'|'.str_pad((string) $row['rank'], 10, '0', STR_PAD_LEFT))->values();
        $tallyIntegrity = $expected->all() === $actual->all();

        $eligibility = null;
        if ($subjectUserId !== null) {
            $row = ElectionEligibilitySnapshot::query()->where('election_id', $election->id)->where('user_id', $subjectUserId)->first();
            $eligibility = $row ? [
                'snapshot_present' => true,
                'selectable_eligible' => (bool) $row->selectable_eligible,
                'voter_eligible' => (bool) $row->voter_eligible,
            ] : ['snapshot_present' => false];
        }

        $machineVerifiable = in_array($ground, ['ballot_limit', 'stop_time', 'ranking', 'tie_break'], true);
        $status = (! $snapshotIntegrity || ! $tallyIntegrity)
            ? 'discrepancy'
            : ($machineVerifiable ? 'verified' : 'requires_human_review');

        return [
            'status' => $status,
            'result' => [
                'snapshot_present' => true,
                'snapshot_integrity' => $snapshotIntegrity,
                'snapshot_vote_count' => (int) $run->vote_count,
                'snapshot_hash' => (string) $run->snapshot_hash,
                'recomputed_snapshot_hash' => $recomputedHash,
                'tally_integrity' => $tallyIntegrity,
                'stored_tally_rows' => $actual->count(),
                'expected_tally_rows' => $expected->count(),
                'eligibility_subject' => $eligibility,
                'identity_disclosure' => 'none',
            ],
        ];
    }
}
