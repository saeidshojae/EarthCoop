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
    ) {
    }

    /**
     * Ensure a threshold-eligible group has one active election cycle.
     *
     * E4 will replace the temporary Candidate compatibility projection with
     * frozen eligibility snapshots. Until then we preserve the legacy profile /
     * acceptance read path without treating Candidate.position as canonical.
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

            $existing = Election::query()
                ->where('group_id', $lockedGroup->id)
                ->where(function ($query) {
                    $query->whereIn('lifecycle_status', [
                        ElectionLifecycleStatus::Scheduled->value,
                        ElectionLifecycleStatus::Open->value,
                    ])->orWhere(function ($legacy) {
                        $legacy->whereNull('lifecycle_status')
                            ->where('is_closed', false);
                    });
                })
                ->orderByDesc('id')
                ->first();

            if ($existing !== null) {
                return [$existing, false];
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
                            // Compatibility-only placeholder. Legacy acceptance
                            // resolves the actual role from Vote.position.
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
}
