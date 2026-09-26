<?php

namespace App\Services\Groups;

use App\Models\Group;
use App\Models\GroupUser;

final class GroupMembershipRoleResolver
{
    public function effectiveRole(Group $group, ?GroupUser $membership): ?int
    {
        if ($membership === null || (int) $membership->status !== 1) {
            return null;
        }

        $role = (int) $membership->role;

        if ((bool) config('location-governance.groups_enabled', false)
            && $group->governance_area_id !== null) {
            return $role;
        }

        if (in_array($role, [2, 3, 4, 5], true)) {
            return $role;
        }

        $level = strtolower(trim((string) ($group->getRawOriginal('location_level') ?? $group->location_level ?? '')));

        return in_array($level, ['neighborhood', 'street', 'alley'], true) ? 1 : 0;
    }
}
