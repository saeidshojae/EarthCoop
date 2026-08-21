<?php

namespace App\Services\Elections;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Enums\Elections\ElectionPosition;
use App\Models\Election;
use App\Models\ElectionBallotEvent;
use App\Models\ElectionEligibilitySnapshot;
use App\Models\Vote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ElectionBallotService
{
    private const REQUEST_UUID_MAX_LENGTH = 96;

    public function __construct(
        private readonly ElectionPolicyResolver $policyResolver,
    ) {
    }

    /**
     * Replace a voter's current ballot projection while preserving an immutable
     * append-only history of each cast, role change and withdrawal.
     *
     * @param array<int, mixed> $managerUserIds
     * @param array<int, mixed> $inspectorUserIds
     */
    public function submit(
        Election $election,
        int $voterId,
        array $managerUserIds,
        array $inspectorUserIds,
        ?string $requestUuid = null,
    ): array {
        return DB::transaction(function () use ($election, $voterId, $managerUserIds, $inspectorUserIds, $requestUuid): array {
            $lockedElection = Election::query()->lockForUpdate()->findOrFail($election->id);

            if ((int) $lockedElection->group_id !== (int) $election->group_id) {
                throw ValidationException::withMessages(['election' => 'انتخابات با گروه موردنظر همخوانی ندارد.']);
            }

            if ($lockedElection->lifecycle_status !== ElectionLifecycleStatus::Open) {
                throw ValidationException::withMessages(['election' => 'این انتخابات در وضعیت دریافت رأی نیست.']);
            }

            $managerIds = $this->normaliseIds($managerUserIds, 'manager');
            $inspectorIds = $this->normaliseIds($inspectorUserIds, 'inspector');

            $duplicatesAcrossPositions = array_values(array_intersect($managerIds, $inspectorIds));
            if ($duplicatesAcrossPositions !== []) {
                throw ValidationException::withMessages([
                    'ballot' => 'یک عضو نمی‌تواند همزمان برای نقش مدیر و بازرس انتخاب شود.',
                ]);
            }

            $policy = $this->policyResolver->resolveForGroup($lockedElection->group);
            if (count($managerIds) > $this->policyResolver->managerSeatCount($policy)) {
                throw ValidationException::withMessages([
                    'manager' => 'تعداد انتخاب‌های مدیر بیشتر از ظرفیت مجاز این انتخابات است.',
                ]);
            }
            if (count($inspectorIds) > $this->policyResolver->inspectorSeatCount($policy)) {
                throw ValidationException::withMessages([
                    'inspector' => 'تعداد انتخاب‌های بازرس بیشتر از ظرفیت مجاز این انتخابات است.',
                ]);
            }

            $voterSnapshot = ElectionEligibilitySnapshot::query()
                ->where('election_id', $lockedElection->id)
                ->where('user_id', $voterId)
                ->first();

            if ($voterSnapshot === null || ! $voterSnapshot->voter_eligible) {
                throw ValidationException::withMessages([
                    'voter' => 'شما در snapshot آغاز این انتخابات واجد شرایط رأی دادن نیستید.',
                ]);
            }

            $selectedIds = array_values(array_unique(array_merge($managerIds, $inspectorIds)));
            if ($selectedIds !== []) {
                $selectableIds = ElectionEligibilitySnapshot::query()
                    ->where('election_id', $lockedElection->id)
                    ->whereIn('user_id', $selectedIds)
                    ->where('selectable_eligible', true)
                    ->pluck('user_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $invalidIds = array_values(array_diff($selectedIds, $selectableIds));
                if ($invalidIds !== []) {
                    throw ValidationException::withMessages([
                        'ballot' => 'یک یا چند عضو انتخاب‌شده در snapshot آغاز انتخابات واجد شرایط انتخاب شدن نیستند.',
                    ]);
                }
            }

            $currentVotes = Vote::query()
                ->where('election_id', $lockedElection->id)
                ->where('voter_id', $voterId)
                ->lockForUpdate()
                ->get();

            // Never destroy unresolved legacy identity while replacing the current
            // projection. E2 deliberately leaves ambiguous rows unresolved; E5
            // must fail closed until reconciliation supplies candidate_user_id.
            if ($currentVotes->contains(fn (Vote $vote) => $vote->candidate_user_id === null)) {
                throw ValidationException::withMessages([
                    'ballot' => 'رأی تاریخی شما شامل شناسه حل‌نشده است و تا تطبیق داده‌ها قابل تغییر نیست.',
                ]);
            }

            $current = [];
            foreach ($currentVotes as $vote) {
                try {
                    $position = ElectionPosition::fromLegacyVotePosition($vote->position ?? '');
                } catch (\InvalidArgumentException) {
                    throw ValidationException::withMessages([
                        'ballot' => 'رأی تاریخی شما شامل نقش انتخاباتی نامعتبر است و تا تطبیق داده‌ها قابل تغییر نیست.',
                    ]);
                }

                $candidateUserId = (int) $vote->candidate_user_id;
                if (isset($current[$candidateUserId])) {
                    throw ValidationException::withMessages([
                        'ballot' => 'رأی تاریخی شما شامل انتخاب تکراری است و تا تطبیق داده‌ها قابل تغییر نیست.',
                    ]);
                }

                $current[$candidateUserId] = $position;
            }

            $desired = [];
            foreach ($managerIds as $candidateUserId) {
                $desired[$candidateUserId] = ElectionPosition::Manager;
            }
            foreach ($inspectorIds as $candidateUserId) {
                $desired[$candidateUserId] = ElectionPosition::Inspector;
            }

            $uuid = $this->normaliseRequestUuid($requestUuid);
            $occurredAt = now();

            foreach ($current as $candidateUserId => $position) {
                if (! isset($desired[$candidateUserId])) {
                    $this->appendEvent(
                        $lockedElection->id,
                        $voterId,
                        'withdrawn',
                        null,
                        $candidateUserId,
                        null,
                        $position,
                        $uuid,
                        $occurredAt,
                    );
                    continue;
                }

                if ($desired[$candidateUserId] !== $position) {
                    $this->appendEvent(
                        $lockedElection->id,
                        $voterId,
                        'changed',
                        $candidateUserId,
                        $candidateUserId,
                        $desired[$candidateUserId],
                        $position,
                        $uuid,
                        $occurredAt,
                    );
                }
            }

            foreach ($desired as $candidateUserId => $position) {
                if (! isset($current[$candidateUserId])) {
                    $this->appendEvent(
                        $lockedElection->id,
                        $voterId,
                        'cast',
                        $candidateUserId,
                        null,
                        $position,
                        null,
                        $uuid,
                        $occurredAt,
                    );
                }
            }

            Vote::query()
                ->where('election_id', $lockedElection->id)
                ->where('voter_id', $voterId)
                ->delete();

            foreach ($desired as $candidateUserId => $position) {
                Vote::create([
                    'election_id' => $lockedElection->id,
                    'voter_id' => $voterId,
                    // Compatibility projection only. Canonical identity is candidate_user_id.
                    'candidate_id' => $candidateUserId,
                    'candidate_user_id' => $candidateUserId,
                    'position' => $position->legacyVotePosition(),
                ]);
            }

            return [
                'election_id' => (int) $lockedElection->id,
                'voter_id' => $voterId,
                'manager_user_ids' => $managerIds,
                'inspector_user_ids' => $inspectorIds,
                'request_uuid' => $uuid,
            ];
        });
    }

    /** @param array<int, mixed> $ids */
    private function normaliseIds(array $ids, string $field): array
    {
        $normalised = [];
        foreach ($ids as $id) {
            if (filter_var($id, FILTER_VALIDATE_INT) === false || (int) $id <= 0) {
                throw ValidationException::withMessages([$field => 'شناسه عضو انتخاب‌شده نامعتبر است.']);
            }
            $normalised[] = (int) $id;
        }

        if (count($normalised) !== count(array_unique($normalised))) {
            throw ValidationException::withMessages([$field => 'یک عضو در یک نقش نمی‌تواند بیش از یک بار انتخاب شود.']);
        }

        return array_values($normalised);
    }

    private function normaliseRequestUuid(?string $requestUuid): string
    {
        $uuid = trim((string) ($requestUuid ?: Str::uuid()));
        if ($uuid === '') {
            $uuid = (string) Str::uuid();
        }

        if (mb_strlen($uuid) > self::REQUEST_UUID_MAX_LENGTH) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'شناسه یکتای درخواست بیش از حد مجاز طول دارد.',
            ]);
        }

        return $uuid;
    }

    private function appendEvent(
        int $electionId,
        int $voterId,
        string $eventType,
        ?int $candidateUserId,
        ?int $previousCandidateUserId,
        ?ElectionPosition $position,
        ?ElectionPosition $previousPosition,
        string $requestUuid,
        $occurredAt,
    ): void {
        ElectionBallotEvent::create([
            'election_id' => $electionId,
            'voter_id' => $voterId,
            'event_type' => $eventType,
            'candidate_user_id' => $candidateUserId,
            'previous_candidate_user_id' => $previousCandidateUserId,
            'position' => $position?->value,
            'previous_position' => $previousPosition?->value,
            'request_uuid' => $requestUuid,
            'metadata' => ['source' => 'ballot_v2'],
            'occurred_at' => $occurredAt,
        ]);
    }
}
