<?php

namespace App\Services\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\Location;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class CommunityAreaService
{
    public function __construct(
        private readonly CommunityCreationPolicy $creationPolicy,
        private readonly GovernanceResolver $governanceResolver,
    ) {
    }

    public function createFor(Location $location, User $actor): GovernanceArea
    {
        if (! $this->creationPolicy->mayCreateFor($location, $actor)) {
            throw new DomainException('A community area cannot be created for this location.');
        }

        return DB::transaction(function () use ($location, $actor): GovernanceArea {
            $existing = $location->governanceAreas()
                ->where('area_kind', 'community')
                ->orderBy('governance_areas.id')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $officialParent = $this->governanceResolver->baseOfficialAreaForResidence($location);
            $key = 'community:location:'.$location->id;

            $area = GovernanceArea::query()->firstOrCreate(
                ['key' => $key],
                [
                    'parent_id' => $officialParent?->id,
                    'country_code' => $location->country_code,
                    'governance_type' => 'community',
                    'area_kind' => 'community',
                    'canonical_name' => $location->canonical_name ?: $location->name,
                    'localized_names' => $location->localized_names,
                    'rank' => $officialParent !== null ? ((int) $officialParent->rank + 1) : 0,
                    'status' => 'active',
                    'metadata' => [
                        'source_location_id' => $location->id,
                        'created_by_user_id' => $actor->id,
                        'creation_mode' => 'on_demand',
                    ],
                ],
            );

            if ($area->area_kind !== 'community') {
                throw new DomainException('The canonical community identity is already used by a non-community governance area.');
            }

            $area->locations()->syncWithoutDetaching([$location->id]);

            return $area->fresh();
        });
    }
}
