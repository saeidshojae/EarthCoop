<?php

namespace App\Services\Elections;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Group;
use App\Models\GroupUser;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ElectionCycleService
{
    public function __construct(
        private readonly ElectionPolicyResolver $policyResolver,
        private readonly ElectionLifecycleService $lifecycle,
        private readonly ElectionGroupHierarchyResolver $hierarchy,
    ) {}

    public function ensureForGroup(Group $group): ?Election
    {
        [$election, $created, $isSuccessor] = DB::transaction(function () use ($group): array {
            $lockedGroup = Group::query()->lockForUpdate()->findOrFail($group->getKey());
            $attributes = $lockedGroup->getAttributes();

            if (($attributes['group_type'] ?? null) === 'private') {
                return [null, false, false];
            }

            try {
                $policy = $this->policyResolver->resolveEffectiveForGroup($lockedGroup);
            } catch (RuntimeException) {
                return [null, false, false];
            }

            if (! $this->policyResolver->electionEnabled($policy)) {
                return [null, false, false];
            }

            try {
                if (! $this->hierarchy->isIndependentElectoralLayer($lockedGroup)) {
                    return [null, false, false];
                }
            } catch (RuntimeException) {
                return [null, false, false];
            }

            $activeMemberQuery = GroupUser::query()
                ->join('users', 'users.id', '=', 'group_user.user_id')
                ->where('group_user.group_id', $lockedGroup->id)
                ->where('group_user.status', 1)
                ->where('group_user.role', '>=', 1)
                ->where('users.is_system', false);

            if ((clone $activeMemberQuery)->count() < $this->policyResolver->startThreshold($policy)) {
                return [null, false, false];
            }

            $latest = Election::query()
                ->where('group_id', $lockedGroup->id)
                ->orderByDesc('cycle_number')
                ->orderByDesc('id')
                ->first();

            if ($latest !== null && $this->latestCycleIsCollectingBallots($latest)) {
                return [$latest, false, false];
            }

            // E0 continuity: once the current ballot window is stopped and its
            // immutable snapshot is handed to tally/application, collection for
            // the next stop opens immediately. The old cycle may continue through
            // tally, offers, acceptance and appointments in parallel.
            $isSuccessor = $latest !== null;
            $startsAt = now();
            $endsAt = $isSuccessor
                ? $this->successorEndsAt($startsAt, $policy)
                : $startsAt->copy()->addDays($this->policyResolver->votingDurationDays($policy));

            $election = Election::create([
                'group_id' => $lockedGroup->id,
                'cycle_number' => $latest === null ? 1 : ((int) ($latest->cycle_number ?? 0) + 1),
                'previous_election_id' => $latest?->id,
                'policy_version_id' => $policy->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'is_closed' => false,
                'lifecycle_status' => ElectionLifecycleStatus::Scheduled,
            ]);

            $now = now();
            (clone $activeMemberQuery)
                ->select('group_user.user_id')
                ->orderBy('group_user.user_id')
                ->chunk(500, function ($members) use ($election, $now): void {
                    $rows = [];
                    foreach ($members as $member) {
                        $rows[] = [
                            'election_id' => $election->id,
                            'user_id' => (int) $member->user_id,
                            'position' => 'manager',
                            'accept_status' => null,
                            'acceptance_status' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    if ($rows !== []) {
                        Candidate::query()->insert($rows);
                    }
                });

            return [$election, true, $isSuccessor];
        }, 3);

        if ($election === null || ! $created) {
            return $election;
        }

        return $this->lifecycle->transition(
            $election,
            ElectionLifecycleStatus::Open,
            $isSuccessor ? 'continuous_ballot_window_after_stop' : 'policy_threshold_reached',
            'scheduler',
            null,
            $isSuccessor ? 'election-cycle:continuous-open' : 'election-cycle:auto-open',
            $isSuccessor ? ['previous_election_id' => (int) $election->previous_election_id] : [],
        );
    }

    private function latestCycleIsCollectingBallots(Election $latest): bool
    {
        $status = $this->lifecycle->currentStatus($latest);

        return in_array($status, [
            ElectionLifecycleStatus::Scheduled,
            ElectionLifecycleStatus::Open,
        ], true);
    }

    private function successorEndsAt($startsAt, $policy)
    {
        $months = $this->repeatIntervalMonths($policy->cycle_interval_months ?? null);
        if ($months > 0) {
            return $startsAt->copy()->addMonthsNoOverflow($months);
        }

        return $startsAt->copy()->addDays($this->policyResolver->votingDurationDays($policy));
    }

    private function repeatIntervalMonths(mixed $value): int
    {
        if ($value === null || $value === '') {
            // E0 default: next systemic application stop is about 180 days.
            return 6;
        }
        if (! is_numeric($value)) {
            throw new RuntimeException('Election repeat interval must be numeric months.');
        }
        return max(0, (int) $value);
    }
}
