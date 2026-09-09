<?php

namespace App\Services\LocationGovernance;

use App\Exceptions\InvalidLocationHierarchy;
use App\Models\Location;
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
