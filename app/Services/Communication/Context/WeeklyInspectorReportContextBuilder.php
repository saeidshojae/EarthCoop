<?php

namespace App\Services\Communication\Context;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\Groups\EffectiveGroupMembershipService;
use App\Services\Groups\GroupMembershipRoleResolver;
use Carbon\CarbonPeriod;

final class WeeklyInspectorReportContextBuilder
{
    private const INSPECTOR_ROLE = 2;

    public function __construct(
        private readonly WeeklyMemberReportContextBuilder $memberContext,
        private readonly EffectiveGroupMembershipService $memberships,
        private readonly GroupMembershipRoleResolver $roles,
    ) {
    }

    /** @return array<string,mixed> */
    public function build(User $user, CarbonPeriod $period): array
    {
        $inspectedGroupIds = $this->roleGroupIds($user, self::INSPECTOR_ROLE);

        return array_merge($this->memberContext->build($user, $period), [
            'inspected_groups_count' => count($inspectedGroupIds),
            'inspected_group_ids' => $inspectedGroupIds,
            'open_elections_in_inspected_groups_count' => $this->memberContext->openElectionsCount($inspectedGroupIds),
            'open_polls_in_inspected_groups_count' => $this->memberContext->openPollsCount($inspectedGroupIds),
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
