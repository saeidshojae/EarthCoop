<?php

namespace App\Services\LocationGovernance;

use App\Models\Location;
use App\Models\User;
use App\Models\UserLocationRelationship;

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

        if (! in_array($this->typeKey($location), ['street', 'alley', 'complex', 'building'], true)) {
            return false;
        }

        $residenceLocationId = UserLocationRelationship::query()
            ->where('user_id', $actor->id)
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->latest('started_at')
            ->value('location_id');

        if ($residenceLocationId === null) {
            return false;
        }

        $current = Location::query()->find($residenceLocationId);
        $visited = [];

        while ($current !== null && ! isset($visited[$current->id])) {
            if ($current->id === $location->id) {
                return true;
            }

            $visited[$current->id] = true;
            $current = $current->parent_id !== null
                ? Location::query()->find($current->parent_id)
                : null;
        }

        return false;
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
