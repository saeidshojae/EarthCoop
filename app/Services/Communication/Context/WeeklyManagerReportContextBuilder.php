<?php

namespace App\Services\Communication\Context;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\Groups\EffectiveGroupMembershipService;
use App\Services\Groups\GroupMembershipRoleResolver;
use Carbon\CarbonPeriod;

final class WeeklyManagerReportContextBuilder
{
    private const MANAGER_ROLE = 3;

    public function __construct(
        private readonly WeeklyMemberReportContextBuilder $memberContext,
        private readonly EffectiveGroupMembershipService $memberships,
        private readonly GroupMembershipRoleResolver $roles,
    ) {
    }

    /** @return array<string,mixed> */
    public function build(User $user, CarbonPeriod $period): array
    {
        $managedGroupIds = $this->roleGroupIds($user, self::MANAGER_ROLE);

        return array_merge($this->memberContext->build($user, $period), [
            'managed_groups_count' => count($managedGroupIds),
            'managed_group_ids' => $managedGroupIds,
            'open_elections_in_managed_groups_count' => $this->memberContext->openElectionsCount($managedGroupIds),
            'open_polls_in_managed_groups_count' => $this->memberContext->openPollsCount($managedGroupIds),
        ]);
    }

    /** @return array<int,int> */
    private function roleGroupIds(User $user, int $role): array
    {
        $memberships = $this->memberships->currentForUser($user);
        if ($memberships->isEmpty()) {
            return [];
        }

        $groups = Group::query()
            ->whereIn('id', $memberships->pluck('group_id')->all())
            ->get()
            ->keyBy('id');

        return $memberships
            ->filter(function (GroupUser $membership) use ($groups, $role): bool {
                $group = $groups->get($membership->group_id);

                return $group !== null && $this->roles->effectiveRole($group, $membership) === $role;
            })
            ->pluck('group_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
