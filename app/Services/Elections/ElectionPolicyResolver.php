<?php

namespace App\Services\Elections;

use App\Models\Group;
use App\Models\GroupSetting;
use RuntimeException;

class ElectionPolicyResolver
{
    public function resolveForGroup(Group $group): GroupSetting
    {
        $level = $this->levelKeyForGroup($group);
        $setting = GroupSetting::query()->where('level', $level)->first();

        if ($setting === null) {
            throw new RuntimeException("Election policy is not configured for group setting level [{$level}].");
        }

        return $setting;
    }

    /**
     * Preserve the legacy precedence exactly while centralising it in one
     * election-domain adapter: specialty > experience > age > gender > base.
     */
    public function levelKeyForGroup(Group $group): string
    {
        $base = (string) $group->location_level;

        if ($group->specialty_id !== null) {
            return $base . '_job';
        }

        if ($group->experience_id !== null) {
            return $base . '_experience';
        }

        if ($group->age_group_id !== null) {
            return $base . '_age';
        }

        if ($group->gender !== null) {
            return $base . '_gender';
        }

        return $base;
    }

    public function managerSeatCount(GroupSetting $setting): int
    {
        return max(0, (int) $setting->manager_count);
    }

    public function inspectorSeatCount(GroupSetting $setting): int
    {
        // Canonical schema/model spelling is inspector_count. Keeping this
        // accessor prevents the legacy ElectionController typo
        // `insperctor_count` from leaking into future election code.
        return max(0, (int) $setting->inspector_count);
    }
}
