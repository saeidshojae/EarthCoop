<?php

namespace App\Services\LocationGovernance;

use App\Models\Location;
use App\Models\User;

class CommunityCreationPolicy
{
    /**
     * Community governance is optional and can only be materialized on demand
     * for active micro-locations. Location verification remains a separate concern.
     */
    public function mayCreateFor(Location $location, User $actor): bool
    {
        if (! $actor->exists || $location->status !== 'active') {
            return false;
        }

        return in_array($this->typeKey($location), ['complex', 'building'], true);
    }

    private function typeKey(Location $location): ?string
    {
        if (is_string($location->type_key ?? null) && $location->type_key !== '') {
            return $location->type_key;
        }

        $key = $location->type?->key;

        return is_string($key) && $key !== '' ? $key : null;
    }
}
