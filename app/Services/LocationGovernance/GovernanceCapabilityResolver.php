<?php

namespace App\Services\LocationGovernance;

use App\Data\LocationGovernance\GovernanceCapabilities;
use App\Models\GovernanceArea;
use App\Models\GovernanceAreaOverride;
use App\Models\GovernanceCapabilityPolicy;

class GovernanceCapabilityResolver
{
    public function capabilities(GovernanceArea $area): GovernanceCapabilities
    {
        $resolved = [];

        $default = GovernanceCapabilityPolicy::query()
            ->where('scope', 'default')
            ->orderBy('id')
            ->first();

        if ($default !== null) {
            $resolved = array_replace($resolved, $default->capabilities ?? []);
        }

        $countryType = GovernanceCapabilityPolicy::query()
            ->where('scope', 'country_type')
            ->where('country_code', $area->country_code)
            ->where('governance_type', $area->governance_type)
            ->orderBy('id')
            ->first();

        if ($countryType !== null) {
            $resolved = array_replace($resolved, $countryType->capabilities ?? []);
        }

        $override = GovernanceAreaOverride::query()
            ->where('governance_area_id', $area->id)
            ->first();

        if ($override !== null) {
            $resolved = array_replace($resolved, $override->capabilities ?? []);
        }

        return new GovernanceCapabilities($resolved);
    }
}
