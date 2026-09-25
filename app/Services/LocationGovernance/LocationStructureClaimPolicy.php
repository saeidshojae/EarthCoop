<?php

namespace App\Services\LocationGovernance;

use App\Models\Location;
use App\Models\LocationStructureClaim;
use App\Models\LocationSchemaType;
use App\Models\LocationTypeRelation;
use Illuminate\Support\Collection;

class LocationStructureClaimPolicy
{
    public function allowedClaimTypes(Location $location): array
    {
        return $this->structuralMetadata($location)['structural_claim_types'] ?? [];
    }

    public function allowsClaimType(Location $location, string $claimType, Collection $contextClaims): bool
    {
        if (in_array($claimType, $this->allowedClaimTypes($location), true)) {
            return true;
        }

        $contextTypes = $contextClaims
            ->filter(fn ($claim): bool => $claim instanceof LocationStructureClaim
                && (int) $claim->location_id === (int) $location->id
                && in_array($claim->status, array_merge(LocationStructureClaimService::OPEN_STATUSES, ['approved']), true))
            ->pluck('claim_type')
            ->unique();

        $conditional = $this->structuralMetadata($location)['structural_claim_types_after'] ?? [];

        return $contextTypes->contains(
            fn (string $contextType): bool => in_array($claimType, $conditional[$contextType] ?? [], true)
        );
    }

    private function structuralMetadata(Location $location): array
    {
        return LocationSchemaType::query()
            ->where('location_schema_id', $location->location_schema_id)
            ->where('location_type_id', $location->location_type_id)
            ->value('metadata') ?? [];
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
