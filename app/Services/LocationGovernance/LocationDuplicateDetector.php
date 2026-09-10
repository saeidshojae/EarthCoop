<?php

namespace App\Services\LocationGovernance;

use App\Models\Location;
use App\Models\LocationType;

class LocationDuplicateDetector
{
    public function findLikelyDuplicate(Location $parent, LocationType $type, string $canonicalName): ?Location
    {
        $normalized = $this->normalizeName($canonicalName);

        if ($normalized === '') {
            return null;
        }

        return Location::query()
            ->where('parent_id', $parent->id)
            ->where('location_type_id', $type->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->first(fn (Location $location): bool => $this->normalizeName((string) $location->canonical_name) === $normalized);
    }

    public function normalizeName(string $name): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);

        return mb_strtolower($collapsed, 'UTF-8');
    }
}
