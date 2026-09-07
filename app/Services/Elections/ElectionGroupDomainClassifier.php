<?php

namespace App\Services\Elections;

use App\Models\Group;
use RuntimeException;

class ElectionGroupDomainClassifier
{
    public const LEVEL_RANK = [
        'alley' => 0, 'street' => 1, 'neighborhood' => 2,
        'region' => 3, 'village' => 3, 'city' => 4, 'rural' => 4,
        'section' => 5, 'district' => 5, 'county' => 6, 'province' => 7,
        'country' => 8, 'continent' => 9, 'global' => 10,
    ];

    public function domain(Group $group): string
    {
        if ($group->specialty_id !== null) return 'job';
        if ($group->experience_id !== null) return 'experience';
        if ($group->age_group_id !== null) return 'age';
        if ($group->gender !== null) return 'gender';
        return 'public';
    }

    public function level(Group $group): string
    {
        $level = (string) $group->location_level;
        if (! array_key_exists($level, self::LEVEL_RANK)) {
            throw new RuntimeException("Unsupported election conflict-policy level [{$level}].");
        }
        return $level;
    }
}
