<?php

namespace App\Services\LocationGovernance;

use App\Models\Location;
use App\Models\LocationSchemaType;
use App\Models\LocationType;

class LocationProposalPolicy
{
    public function __construct(private readonly LocationSchemaResolver $schemaResolver)
    {
    }

    public function allows(Location $parent, LocationType $type): bool
    {
        if (! $parent->location_schema_id || ! $parent->location_type_id) {
            return false;
        }

        if (! $this->schemaResolver->allowedChildTypes($parent)->contains('id', $type->id)) {
            return false;
        }

        $schemaType = LocationSchemaType::query()
            ->where('location_schema_id', $parent->location_schema_id)
            ->where('location_type_id', $type->id)
            ->first();

        $metadata = $schemaType?->metadata ?? [];

        return (bool) ($metadata['crowdsourced_proposal_allowed'] ?? false);
    }
}
