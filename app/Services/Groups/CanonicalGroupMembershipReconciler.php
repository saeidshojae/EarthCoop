<?php

namespace App\Services\Groups;

use App\Models\GroupUser;
use App\Models\User;
use App\Services\GroupService;

final class CanonicalGroupMembershipReconciler
{
    private const SYSTEM_DIMENSIONS = [
        'public',
        'profession',
        'specialty',
        'age',
        'gender',
    ];

    public function __construct(
        private readonly GroupService $groupService,
    ) {
    }

    /**
     * Reconcile the user's active canonical system-group memberships against
     * the current Primary Residence + membership-dimension resolution.
     *
     * Legacy memberships are intentionally left untouched so Stage C remains
     * reversible. Canonical memberships that no longer belong to the current
     * resolution are retained as history but marked inactive.
     *
     * @return array<int, \App\Models\Group>
     */
    public function reconcile(User $user): array
    {
        if (! (bool) config('location-governance.groups_enabled', false)) {
            return [];
        }

        $groups = collect($this->groupService->getGroupsForUser($user))
            ->unique('id')
            ->values();

        $activeGroupIds = $groups
            ->pluck('id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($activeGroupIds !== []) {
            GroupUser::query()
                ->where('user_id', $user->id)
                ->whereIn('group_id', $activeGroupIds)
                ->update(['status' => 1]);
        }

        $staleMemberships = GroupUser::query()
            ->where('user_id', $user->id)
            ->where('status', 1)
            ->whereHas('group', function ($query): void {
                $query
                    ->whereNotNull('governance_area_id')
                    ->whereIn('dimension_key', self::SYSTEM_DIMENSIONS)
                    ->whereNotNull('dimension_value_key');
            });

        if ($activeGroupIds !== []) {
            $staleMemberships->whereNotIn('group_id', $activeGroupIds);
        }

        $staleMemberships->update(['status' => 0]);

        return $groups->all();
    }
}
