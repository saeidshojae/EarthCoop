<?php

namespace App\Services\Elections;

use App\Models\Group;
use App\Services\LocationGovernance\GroupGovernanceContext;
use RuntimeException;

class ElectionGroupDomainClassifier
{
    public const LEVEL_RANK = [
        'alley' => 0, 'street' => 1, 'neighborhood' => 2, 'local' => 2,
        'region' => 3, 'urban_region' => 3, 'village' => 3,
        'city' => 4, 'rural' => 4, 'rural_district' => 4,
        'section' => 5, 'district' => 5, 'county' => 6, 'province' => 7,
        'country' => 8, 'continent' => 9, 'global' => 10,
    ];

    public function __construct(private readonly GroupGovernanceContext $context) {}

    public function domain(Group $group): string
    {
        return $this->context->electionDomain($group);
    }

    public function level(Group $group): string
    {
        $level = $this->context->officialElectionLevel($group);
        if (! array_key_exists($level, self::LEVEL_RANK)) {
            throw new RuntimeException("Unsupported election conflict-policy level [{$level}].");
        }

        return $level;
    }
}
