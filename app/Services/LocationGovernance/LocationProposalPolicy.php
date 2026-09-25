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
        $claims = collect($structuralClaims)
            ->filter(fn ($claim): bool => $claim instanceof LocationStructureClaim)
            ->concat(
                LocationStructureClaim::query()
                    ->where('location_id', $parent->id)
                    ->whereNull('location_proposal_id')
                    ->where('status', 'approved')
                    ->get()
            )
            ->unique('id')
            ->values();

        if ($claims->contains(fn (LocationStructureClaim $claim): bool =>
            $this->structureClaimPolicy->contradictsPathTypes($claim, [$type->key])
        )) {
            return false;
        }

        if ($this->allows($parent, $type)) {
            return true;
        }

        $effectiveTypeCodes = $this->structureClaimPolicy->effectiveResidenceChildTypeCodes(
            $parent,
            $claims
        );

        return in_array($type->key, $effectiveTypeCodes, true)
            && $this->schemaAllowsCrowdsourcing((int) $parent->location_schema_id, $type);
    }

    public function allowsProposalParentForResidence(LocationProposal $parent, LocationType $type, array $structuralClaims = []): bool
    {
        $claims = collect($structuralClaims)
            ->filter(fn ($claim): bool => $claim instanceof LocationStructureClaim)
            ->concat(
                LocationStructureClaim::query()
                    ->where('location_proposal_id', $parent->id)
                    ->whereNull('location_id')
                    ->where('status', 'approved')
                    ->get()
            )
            ->unique('id')
            ->values();

        if ($claims->contains(fn (LocationStructureClaim $claim): bool =>
            $this->structureClaimPolicy->contradictsPathTypes($claim, [$type->key])
        )) {
            return false;
        }

        if ($this->allowsProposalParent($parent, $type)) {
            return true;
        }

        if (! $parent->location_schema_id || ! $parent->location_type_id) {
            return false;
        }

        $claimTypes = $claims
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

    public function storedStructuralProvenanceIsValid(LocationProposal $proposal): bool
    {
        $proposal->loadMissing(['type', 'parentLocation', 'parentProposal', 'parentReferenceSettlement']);
        if ($proposal->type === null) {
            return false;
        }

        $ids = collect($proposal->metadata['structural_claim_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $claims = $ids->isEmpty()
            ? collect()
            : LocationStructureClaim::query()->whereIn('id', $ids)->get();

        if ($claims->count() !== $ids->count()
            || $claims->contains(fn (LocationStructureClaim $claim): bool =>
                ! in_array($claim->status, array_merge(LocationStructureClaimService::OPEN_STATUSES, ['approved']), true)
            )) {
            return false;
        }

        foreach ($claims as $claim) {
            if (! $this->structureClaimPolicy->dependenciesSatisfied(
                $claim,
                array_merge(LocationStructureClaimService::OPEN_STATUSES, ['approved']),
                $claims,
            )) {
                return false;
            }
        }

        if ($proposal->parent_location_id !== null && $proposal->parentLocation !== null) {
            if ($claims->contains(fn (LocationStructureClaim $claim): bool =>
                (int) $claim->location_id !== (int) $proposal->parent_location_id
                || $claim->location_proposal_id !== null
                || $claim->reference_settlement_id !== null
            )) {
                return false;
            }

            return $this->allowsForResidence(
                $proposal->parentLocation,
                $proposal->type,
                $claims->all(),
            );
        }

        if ($proposal->parent_location_proposal_id !== null && $proposal->parentProposal !== null) {
            if ($claims->contains(fn (LocationStructureClaim $claim): bool =>
                (int) $claim->location_proposal_id !== (int) $proposal->parent_location_proposal_id
                || $claim->location_id !== null
                || $claim->reference_settlement_id !== null
            )) {
                return false;
            }

            return $this->allowsProposalParentForResidence(
                $proposal->parentProposal,
                $proposal->type,
                $claims->all(),
            );
        }

        if ($proposal->parent_reference_settlement_id !== null && $proposal->parentReferenceSettlement !== null) {
            if ($claims->contains(fn (LocationStructureClaim $claim): bool =>
                (int) $claim->reference_settlement_id !== (int) $proposal->parent_reference_settlement_id
                || $claim->location_id !== null
                || $claim->location_proposal_id !== null
            )) {
                return false;
            }

            return $this->allowsReferenceSettlementParentForResidence(
                $proposal->parentReferenceSettlement,
                $proposal->type,
                $claims->all(),
            );
        }

        return false;
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
            ->filter(fn ($claim): bool => $claim instanceof LocationStructureClaim)
            ->concat(
                LocationStructureClaim::query()
                    ->where('reference_settlement_id', $settlement->id)
                    ->where('status', 'approved')
                    ->get()
            )
            ->filter(fn (LocationStructureClaim $claim): bool =>
                (int) $claim->reference_settlement_id === (int) $settlement->id
                && in_array($claim->status, array_merge(LocationStructureClaimService::OPEN_STATUSES, ['approved']), true))
            ->unique('id')
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
