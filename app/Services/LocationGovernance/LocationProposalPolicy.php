<?php

namespace App\Services\LocationGovernance;

use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\ReferenceSettlement;
use App\Models\LocationSchemaType;
use App\Models\LocationStructureClaim;
use App\Models\LocationType;
use App\Models\LocationTypeRelation;

class LocationProposalPolicy
{
    public function __construct(
        private readonly LocationSchemaResolver $schemaResolver,
        private readonly LocationStructureClaimPolicy $structureClaimPolicy,
    )
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

    public function allowsForResidence(Location $parent, LocationType $type, array $structuralClaims = []): bool
    {
        if ($this->allows($parent, $type)) {
            return true;
        }

        $effectiveTypeCodes = $this->structureClaimPolicy->effectiveResidenceChildTypeCodes(
            $parent,
            collect($structuralClaims)->filter(fn ($claim): bool => $claim instanceof LocationStructureClaim)
        );

        return in_array($type->key, $effectiveTypeCodes, true)
            && $this->schemaAllowsCrowdsourcing((int) $parent->location_schema_id, $type);
    }

    public function allowsProposalParentForResidence(LocationProposal $parent, LocationType $type, array $structuralClaims = []): bool
    {
        if ($this->allowsProposalParent($parent, $type)) {
            return true;
        }

        if (! $parent->location_schema_id || ! $parent->location_type_id) {
            return false;
        }

        $claimTypes = collect($structuralClaims)
            ->filter(fn ($claim): bool => $claim instanceof LocationStructureClaim
                && (int) $claim->location_proposal_id === (int) $parent->id
                && in_array($claim->status, array_merge(LocationStructureClaimService::OPEN_STATUSES, ['approved']), true))
            ->pluck('claim_type')
            ->unique();

        $parentType = $parent->type?->key;
        $effectiveTypeCodes = match (true) {
            $parentType === 'city' && $claimTypes->contains('no_urban_region') && $claimTypes->contains('no_neighborhood') => ['street'],
            $parentType === 'city' && $claimTypes->contains('no_urban_region') => ['neighborhood'],
            in_array($parentType, ['urban_region', 'village'], true) && $claimTypes->contains('no_neighborhood') => ['street'],
            default => [],
        };

        return in_array($type->key, $effectiveTypeCodes, true)
            && $this->schemaAllowsCrowdsourcing((int) $parent->location_schema_id, $type);
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

    public function allowsReferenceSettlementParentForResidence(
        ReferenceSettlement $settlement,
        LocationType $type,
        array $structuralClaims = [],
    ): bool {
        if ($settlement->source !== 'IranCountryDivisions/geo_1404'
            || $settlement->dataset_version !== 'v2'
            || $settlement->governance_authorized
            || $settlement->operational_promotion_allowed) {
            return false;
        }

        $claimable = (
            in_array($settlement->classification, ['unverified_settlement', 'needs_review'], true)
            && $settlement->residential_eligibility === 'unverified'
        ) || (
            $settlement->classification === 'verified_residential_village'
            && $settlement->residential_eligibility === 'verified'
        );
        if (! $claimable) return false;

        try {
            $anchor = app(IranSettlementAnchorResolver::class)->resolve($settlement);
        } catch (\Throwable) {
            return false;
        }
        if ($anchor->type?->key !== 'rural_district' || ! $anchor->location_schema_id) return false;

        $claimTypes = collect($structuralClaims)
            ->filter(fn ($claim): bool => $claim instanceof LocationStructureClaim
                && (int) $claim->reference_settlement_id === (int) $settlement->id
                && in_array($claim->status, array_merge(LocationStructureClaimService::OPEN_STATUSES, ['approved']), true))
            ->pluck('claim_type')
            ->unique();

        if ($type->key === 'street' && $claimTypes->contains('no_neighborhood')) {
            return $this->schemaAllowsCrowdsourcing((int) $anchor->location_schema_id, $type);
        }

        if ($type->key !== 'neighborhood') {
            return false;
        }

        $villageType = LocationType::query()->where('key', 'village')->first();
        if ($villageType === null) return false;

        $relationExists = LocationTypeRelation::query()
            ->where('location_schema_id', $anchor->location_schema_id)
            ->where('parent_type_id', $villageType->id)
            ->where('child_type_id', $type->id)
            ->exists();

        return $relationExists && $this->schemaAllowsCrowdsourcing((int) $anchor->location_schema_id, $type);
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
