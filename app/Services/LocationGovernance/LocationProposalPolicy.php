<?php

namespace App\Services\LocationGovernance;

use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\LocationSchemaType;
use App\Models\LocationType;
use App\Models\LocationTypeRelation;

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

        return $this->schemaAllowsCrowdsourcing((int) $parent->location_schema_id, $type);
    }

    public function allowsProposalParent(LocationProposal $parent, LocationType $type): bool
    {
        if (! $parent->location_schema_id || ! $parent->location_type_id) {
            return false;
        }

        $relationExists = LocationTypeRelation::query()
            ->where('location_schema_id', $parent->location_schema_id)
            ->where('parent_type_id', $parent->location_type_id)
            ->where('child_type_id', $type->id)
            ->exists();

        return $relationExists && $this->schemaAllowsCrowdsourcing((int) $parent->location_schema_id, $type);
    }

    private function schemaAllowsCrowdsourcing(int $schemaId, LocationType $type): bool
    {
        $schemaType = LocationSchemaType::query()
            ->where('location_schema_id', $schemaId)
            ->where('location_type_id', $type->id)
            ->first();

        $metadata = $schemaType?->metadata ?? [];

        return (bool) ($metadata['crowdsourced_proposal_allowed'] ?? false);
    }
}
