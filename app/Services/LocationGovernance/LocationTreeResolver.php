<?php

namespace App\Services\LocationGovernance;

use App\Exceptions\InvalidLocationHierarchy;
use App\Models\Location;
use App\Models\LocationStructureClaim;
use Illuminate\Support\Collection;

class LocationTreeResolver
{
    public function ancestors(Location $location): Collection
    {
        $ancestors = collect();
        $visited = [$location->id => true];
        $current = $location;

        while ($current->parent_id !== null) {
            $parent = $current->parent()->first();

            if ($parent === null) {
                break;
            }

            if (isset($visited[$parent->id])) {
                throw new InvalidLocationHierarchy('A cycle was detected in the location tree.');
            }

            $visited[$parent->id] = true;
            $ancestors->prepend($parent);
            $current = $parent;
        }

        return $ancestors->values();
    }

    /**
     * Registration stops at the governance/residence base. Micro-location detail
     * (street, alley, complex, building) belongs to post-registration profile flows.
     */
    public function registrationEndpointAllowed(Location $location, array|Collection $structuralClaims = []): bool
    {
        if (! $this->residenceEndpointAllowed($location)) {
            return false;
        }

        $typeKey = $location->type?->key;
        $microTypes = ['street', 'alley', 'complex', 'building'];

        if (in_array($typeKey, $microTypes, true)) {
            return false;
        }

        $hasDeeperGovernanceChild = $location->children()
            ->where('status', 'active')
            ->whereHas('type', fn ($query) => $query->whereNotIn('key', $microTypes))
            ->exists();

        if ($hasDeeperGovernanceChild) {
            return false;
        }

        $claims = collect($structuralClaims)
            ->filter(fn ($claim) => $claim instanceof LocationStructureClaim)
            ->filter(fn (LocationStructureClaim $claim) => (int) $claim->location_id === (int) $location->id)
            ->filter(fn (LocationStructureClaim $claim) => in_array($claim->status, ['pending', 'ready_for_review', 'needs_evidence', 'approved'], true))
            ->pluck('claim_type');

        // Missing rows are not evidence that a structural tier does not exist.
        // Sparse city/region/village endpoints require an explicit absence claim.
        return match ($typeKey) {
            'city' => $claims->contains('no_urban_region') || $claims->contains('no_neighborhood'),
            'urban_region', 'village' => $claims->contains('no_neighborhood'),
            default => true,
        };
    }

    public function residenceEndpointAllowed(Location $location): bool
    {
        if (!$location->location_schema_id || !$location->location_type_id) {
            return false;
        }

        return $location->schema()
            ->whereHas('types', function ($query) use ($location) {
                $query->where('location_types.id', $location->location_type_id)
                    ->where('location_schema_types.is_residence_endpoint', true);
            })
            ->exists();
    }
}
