<?php

namespace App\Services\LocationGovernance;

use App\Models\Location;
use App\Models\LocationStructureClaim;
use App\Models\LocationTypeRelation;
use Illuminate\Support\Collection;

class LocationStructureClaimPolicy
{
    public function allowedClaimTypes(Location $location): array
    {
        $type = $location->relationLoaded('type') ? $location->type : $location->type()->first();
        $key = $type?->key;

        return match ($key) {
            'city' => ['single_urban_region', 'no_urban_region'],
            'urban_region', 'village' => ['single_neighborhood', 'no_neighborhood'],
            default => [],
        };
    }

    public function effectiveChildTypeCodes(Location $location, Collection $effectiveClaims): array
    {
        $claims = $effectiveClaims
            ->filter(fn ($claim): bool => $claim instanceof LocationStructureClaim
                && (int) $claim->location_id === (int) $location->id
                && $claim->status === 'approved')
            ->pluck('claim_type')
            ->unique()
            ->values();

        $typeKey = $location->type()->value('key');

        if ($typeKey === 'city' && $claims->intersect(['single_urban_region', 'no_urban_region'])->isNotEmpty()) {
            if ($claims->intersect(['single_neighborhood', 'no_neighborhood'])->isNotEmpty()) {
                return $this->descendantTypeCodes($location, ['urban_region', 'neighborhood']);
            }

            return $this->descendantTypeCodes($location, ['urban_region']);
        }

        if (in_array($typeKey, ['urban_region', 'village'], true)
            && $claims->intersect(['single_neighborhood', 'no_neighborhood'])->isNotEmpty()) {
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
