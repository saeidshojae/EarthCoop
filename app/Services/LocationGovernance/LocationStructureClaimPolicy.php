<?php

namespace App\Services\LocationGovernance;

use App\Models\Location;
use App\Models\LocationProposal;
use App\Models\LocationSchemaType;
use App\Models\LocationStructureClaim;
use App\Models\LocationTypeRelation;
use Illuminate\Support\Collection;

class LocationStructureClaimPolicy
{
    public function allowedClaimTypes(Location $location): array
    {
        return $this->structuralMetadataFor(
            (int) $location->location_schema_id,
            (int) $location->location_type_id,
        )['structural_claim_types'] ?? [];
    }

    public function allowedClaimTypesForProposal(LocationProposal $proposal, Collection $contextClaims): array
    {
        $metadata = $this->structuralMetadataFor(
            (int) $proposal->location_schema_id,
            (int) $proposal->location_type_id,
        );

        return $this->allowedClaimTypesForContext(
            $metadata,
            $contextClaims->filter(fn ($claim): bool =>
                $claim instanceof LocationStructureClaim
                && (int) $claim->location_proposal_id === (int) $proposal->id
                && in_array($claim->status, array_merge(LocationStructureClaimService::OPEN_STATUSES, ['approved']), true)
            )->pluck('claim_type')->unique(),
        );
    }

    public function allowsClaimType(Location $location, string $claimType, Collection $contextClaims): bool
    {
        $metadata = $this->structuralMetadataFor(
            (int) $location->location_schema_id,
            (int) $location->location_type_id,
        );

        $contextTypes = $contextClaims
            ->filter(fn ($claim): bool => $claim instanceof LocationStructureClaim
                && (int) $claim->location_id === (int) $location->id
                && in_array($claim->status, array_merge(LocationStructureClaimService::OPEN_STATUSES, ['approved']), true))
            ->pluck('claim_type')
            ->unique();

        return in_array($claimType, $this->allowedClaimTypesForContext($metadata, $contextTypes), true);
    }

    public function allowsProposalClaimType(LocationProposal $proposal, string $claimType, Collection $contextClaims): bool
    {
        return in_array($claimType, $this->allowedClaimTypesForProposal($proposal, $contextClaims), true);
    }

    /**
     * Conditional claim types are schema-driven. A returned list represents
     * alternative prerequisite claim types: at least one must be valid.
     *
     * @return array<int, string>
     */
    public function requiredContextClaimTypes(LocationStructureClaim $claim): array
    {
        [$schemaId, $typeId] = $this->ownerSchemaAndType($claim);
        if ($schemaId === null || $typeId === null) {
            return [];
        }

        $conditional = $this->structuralMetadataFor($schemaId, $typeId)['structural_claim_types_after'] ?? [];

        return collect($conditional)
            ->filter(fn ($allowed): bool => in_array($claim->claim_type, is_array($allowed) ? $allowed : [], true))
            ->keys()
            ->map(fn ($type): string => (string) $type)
            ->values()
            ->all();
    }

    /**
     * Check the owner-local prerequisite for a claim. When selected context is
     * supplied, an open prerequisite must be explicitly selected; an approved
     * prerequisite is always effective.
     */
    public function dependenciesSatisfied(
        LocationStructureClaim $claim,
        array $statuses,
        ?Collection $selectedContext = null,
    ): bool {
        $required = $this->requiredContextClaimTypes($claim);
        if ($required === []) {
            return true;
        }

        $query = LocationStructureClaim::query()
            ->whereIn('claim_type', $required)
            ->whereIn('status', $statuses);

        if ($claim->location_id !== null) {
            $query->where('location_id', $claim->location_id)->whereNull('location_proposal_id');
        } elseif ($claim->location_proposal_id !== null) {
            $query->where('location_proposal_id', $claim->location_proposal_id)->whereNull('location_id');
        } else {
            return false;
        }

        if ($selectedContext !== null) {
            $selectedIds = $selectedContext
                ->filter(fn ($candidate): bool => $candidate instanceof LocationStructureClaim)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();

            $query->where(function ($dependency) use ($selectedIds): void {
                $dependency->where('status', 'approved');
                if ($selectedIds !== []) {
                    $dependency->orWhereIn('id', $selectedIds);
                }
            });
        }

        return $query->exists();
    }

    /**
     * @return array<int, string>
     */
    public function dependentClaimTypes(LocationStructureClaim $claim): array
    {
        [$schemaId, $typeId] = $this->ownerSchemaAndType($claim);
        if ($schemaId === null || $typeId === null) {
            return [];
        }

        $conditional = $this->structuralMetadataFor($schemaId, $typeId)['structural_claim_types_after'] ?? [];

        return collect($conditional[$claim->claim_type] ?? [])
            ->map(fn ($type): string => (string) $type)
            ->unique()
            ->values()
            ->all();
    }

    public function effectiveResidenceChildTypeCodes(Location $location, Collection $effectiveClaims): array
    {
        return $this->effectiveChildTypeCodesForStatuses(
            $location,
            $effectiveClaims,
            array_merge(LocationStructureClaimService::OPEN_STATUSES, ['approved'])
        );
    }

    public function effectiveChildTypeCodes(Location $location, Collection $effectiveClaims): array
    {
        return $this->effectiveChildTypeCodesForStatuses($location, $effectiveClaims, ['approved']);
    }

    private function allowedClaimTypesForContext(array $metadata, Collection $contextTypes): array
    {
        $allowed = collect($metadata['structural_claim_types'] ?? []);
        $conditional = $metadata['structural_claim_types_after'] ?? [];

        foreach ($contextTypes as $contextType) {
            $allowed = $allowed->merge($conditional[(string) $contextType] ?? []);
        }

        return $allowed->map(fn ($type): string => (string) $type)->unique()->values()->all();
    }

    private function ownerSchemaAndType(LocationStructureClaim $claim): array
    {
        if ($claim->location_id !== null) {
            $location = $claim->relationLoaded('location') ? $claim->location : $claim->location()->first();

            return $location === null
                ? [null, null]
                : [(int) $location->location_schema_id, (int) $location->location_type_id];
        }

        if ($claim->location_proposal_id !== null) {
            $proposal = $claim->relationLoaded('locationProposal')
                ? $claim->locationProposal
                : $claim->locationProposal()->first();

            return $proposal === null
                ? [null, null]
                : [(int) $proposal->location_schema_id, (int) $proposal->location_type_id];
        }

        return [null, null];
    }

    private function structuralMetadataFor(int $schemaId, int $typeId): array
    {
        if ($schemaId <= 0 || $typeId <= 0) {
            return [];
        }

        return LocationSchemaType::query()
            ->where('location_schema_id', $schemaId)
            ->where('location_type_id', $typeId)
            ->value('metadata') ?? [];
    }

    private function effectiveChildTypeCodesForStatuses(Location $location, Collection $effectiveClaims, array $statuses): array
    {
        $claims = $effectiveClaims
            ->filter(fn ($claim): bool => $claim instanceof LocationStructureClaim
                && (int) $claim->location_id === (int) $location->id
                && in_array($claim->status, $statuses, true))
            ->pluck('claim_type')
            ->unique()
            ->values();

        $typeKey = $location->type()->value('key');

        if ($typeKey === 'city' && $claims->contains('no_urban_region')) {
            if ($claims->contains('no_neighborhood')) {
                return $this->descendantTypeCodes($location, ['urban_region', 'neighborhood']);
            }

            return ['neighborhood'];
        }

        if (in_array($typeKey, ['urban_region', 'village'], true)
            && $claims->contains('no_neighborhood')) {
            return $this->descendantTypeCodes($location, ['neighborhood']);
        }

        return $this->directChildTypeCodes($location);
    }

    private function descendantTypeCodes(Location $location, array $skipTypeCodes): array
    {
        $currentTypeId = $location->location_type_id;

        foreach ($skipTypeCodes as $skipCode) {
            $next = LocationTypeRelation::query()
                ->where('location_schema_id', $location->location_schema_id)
                ->where('parent_type_id', $currentTypeId)
                ->whereHas('childType', fn ($query) => $query->where('key', $skipCode))
                ->with('childType:id,key')
                ->first();

            if (! $next) {
                return [];
            }

            $currentTypeId = $next->child_type_id;
        }

        return LocationTypeRelation::query()
            ->where('location_schema_id', $location->location_schema_id)
            ->where('parent_type_id', $currentTypeId)
            ->with('childType:id,key')
            ->get()
            ->pluck('childType.key')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function directChildTypeCodes(Location $location): array
    {
        return LocationTypeRelation::query()
            ->where('location_schema_id', $location->location_schema_id)
            ->where('parent_type_id', $location->location_type_id)
            ->with('childType:id,key')
            ->get()
            ->pluck('childType.key')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
