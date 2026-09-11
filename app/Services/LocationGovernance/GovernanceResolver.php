<?php

namespace App\Services\LocationGovernance;

use App\Exceptions\InvalidLocationHierarchy;
use App\Models\GovernanceArea;
use App\Models\Location;
use Illuminate\Support\Collection;

class GovernanceResolver
{
    public function __construct(
        private readonly LocationTreeResolver $locationTreeResolver,
    ) {
    }

    public function baseOfficialAreaForResidence(Location $residence): ?GovernanceArea
    {
        $path = $this->locationTreeResolver->ancestors($residence)
            ->push($residence)
            ->reverse()
            ->values();

        foreach ($path as $location) {
            $area = $location->governanceAreas()
                ->official()
                ->active()
                ->orderByDesc('rank')
                ->orderBy('governance_areas.id')
                ->first();

            if ($area !== null) {
                return $area;
            }
        }

        return null;
    }

    public function officialAncestors(GovernanceArea $area): Collection
    {
        $ancestors = collect();
        $visited = [$area->id => true];
        $current = $area;

        while ($current->parent_id !== null) {
            $parent = $current->parent()->first();

            if ($parent === null) {
                break;
            }

            if (isset($visited[$parent->id])) {
                throw new InvalidLocationHierarchy('A cycle was detected in the governance topology.');
            }

            if ($parent->area_kind !== 'official') {
                throw new InvalidLocationHierarchy('An official governance chain cannot traverse a non-official area.');
            }

            $visited[$parent->id] = true;
            $ancestors->push($parent);
            $current = $parent;
        }

        return $ancestors->values();
    }

    public function officialAreasForResidence(Location $residence): Collection
    {
        $base = $this->baseOfficialAreaForResidence($residence);

        if ($base === null) {
            return collect();
        }

        return collect([$base])
            ->concat($this->officialAncestors($base))
            ->values();
    }
}
