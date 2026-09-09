<?php

namespace App\Services\LocationGovernance;

use App\Exceptions\InvalidLocationHierarchy;
use App\Models\Location;
use App\Models\LocationType;
use Illuminate\Support\Collection;

class LocationSchemaResolver
{
    public function allowedChildTypes(Location $parent): Collection
    {
        if (!$parent->location_schema_id || !$parent->location_type_id) {
            return collect();
        }

        return LocationType::query()
            ->whereIn('id', function ($query) use ($parent) {
                $query->select('child_type_id')
                    ->from('location_type_relations')
                    ->where('location_schema_id', $parent->location_schema_id)
                    ->where('parent_type_id', $parent->location_type_id);
            })
            ->get();
    }

    public function assertValidParentChild(Location $parent, LocationType $childType): void
    {
        if (!$parent->location_schema_id || !$parent->location_type_id) {
            throw new InvalidLocationHierarchy('Parent location is not bound to a canonical schema and type.');
        }

        $belongsToSchema = $parent->schema()
            ->whereHas('types', fn ($query) => $query->where('location_types.id', $childType->id))
            ->exists();

        $relationExists = $parent->schema
            ->typeRelations()
            ->where('parent_type_id', $parent->location_type_id)
            ->where('child_type_id', $childType->id)
            ->exists();

        if (!$belongsToSchema || !$relationExists) {
            throw new InvalidLocationHierarchy('The requested child type is not valid under this location in its schema.');
        }
    }
}
