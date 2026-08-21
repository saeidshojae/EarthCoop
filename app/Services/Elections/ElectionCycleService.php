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
    ) {
    }

    /**
     * Ensure an independently electable, threshold-eligible group has one
     * canonical election cycle at the correct systemic cadence.
     *
     * Structural topology is evaluated before population threshold. A group
     * with exactly one approved effective child constituency is represented by
     * the inherited lower appointment and must not run a duplicate election.
     *
     * Continuity rules:
     * - any non-terminal latest cycle blocks a new cycle;
     * - filled/exhausted cycles respect the configured repeat interval
     *   (`second_election_time`, canonicalized as months);
     * - cancelled cycles may be replaced immediately because they did not
     *   establish a valid term.
     *
     * Candidate remains a compatibility projection for legacy profile /
     * acceptance reads and is not canonical for office identity.
     */
    public function ensureForGroup(Group $group): ?Election
    {
        [$election, $created] = DB::transaction(function () use ($group): array {
            /** @var Group $lockedGroup */
            $lockedGroup = Group::query()->lockForUpdate()->findOrFail($group->getKey());
            $attributes = $lockedGroup->getAttributes();

            if (($attributes['group_type'] ?? null) === 'private') {
                return [null, false];
            }

            try {
                $policy = $this->policyResolver->resolveForGroup($lockedGroup);
            } catch (RuntimeException) {
                return [null, false];
            }

            if ((int) $policy->election_status !== 1) {
                return [null, false];
            }

            try {
                if (! $this->hierarchy->isIndependentElectoralLayer($lockedGroup)) {
                    return [null, false];
                }
            } catch (RuntimeException) {
                // Unsupported/non-geographic groups are not auto-elected until
                // they have an explicit canonical topology policy.
                return [null, false];
            }

            $activeMemberQuery = GroupUser::query()
                ->join('users', 'users.id', '=', 'group_user.user_id')
                ->where('group_user.group_id', $lockedGroup->id)
                ->where('group_user.status', 1)
                ->where('group_user.role', '>=', 1)
                ->where('users.is_system', false);

            $threshold = max(1, (int) $policy->max_for_election);
            if ((clone $activeMemberQuery)->count() < $threshold) {
                return [null, false];
            }

            $latest = Election::query()
                ->where('group_id', $lockedGroup->id)
                ->orderByDesc('id')
                ->first();

            if ($latest !== null && $this->latestCycleBlocksCreation($latest, $policy->second_election_time)) {
                return [$latest, false];
            }

            $startsAt = now();
            $election = Election::create([
                'group_id' => $lockedGroup->id,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addDays(max(0, (int) $policy->election_time)),
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

            return [$election, true];
        }, 3);

        if ($election === null || ! $created) {
            return $election;
        }

        return $this->lifecycle->transition(
            $election,
            ElectionLifecycleStatus::Open,
            'policy_threshold_reached',
            'scheduler',
            null,
            'election-cycle:auto-open',
        );
    }

    private function latestCycleBlocksCreation(Election $latest, mixed $repeatInterval): bool
    {
        $status = $this->lifecycle->currentStatus($latest);

        if (! $status->isTerminal()) {
            return true;
        }

        if ($status === ElectionLifecycleStatus::Cancelled) {
            return false;
        }

        $months = $this->repeatIntervalMonths($repeatInterval);
        if ($months === 0) {
            return false;
        }

        $terminalAt = $latest->lifecycleTransitions()
            ->where('to_status', $status->value)
            ->orderByDesc('transitioned_at')
            ->value('transitioned_at');

        $anchor = $terminalAt !== null
            ? \Carbon\Carbon::parse($terminalAt)
            : ($latest->updated_at ?? $latest->ends_at ?? $latest->starts_at ?? now());

        return now()->lt($anchor->copy()->addMonthsNoOverflow($months));
    }

    private function repeatIntervalMonths(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 3;
        }

        if (! is_numeric($value)) {
            throw new RuntimeException('Election repeat interval must be numeric months.');
        }

        return max(0, (int) $value);
    }
}
