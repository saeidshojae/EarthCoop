<?php

namespace App\Services\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\Group;
use App\Models\GroupUser;
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
            $group = $this->materializePublicAssembly($area);
            $this->activateMembership($group, $actor);

            return $area->fresh();
        });
    }


    public function reconcileMembershipsFor(User $user): array
    {
        $residenceId = \App\Models\UserLocationRelationship::query()
            ->where('user_id', $user->id)
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->latest('started_at')
            ->value('location_id');

        $eligibleLocationIds = [];
        if ($residenceId !== null) {
            $cursor = Location::query()->find($residenceId);
            $visited = [];
            while ($cursor !== null && ! isset($visited[$cursor->id])) {
                $visited[$cursor->id] = true;
                if (in_array($cursor->type?->key, ['street', 'alley', 'complex', 'building'], true)) {
                    $eligibleLocationIds[] = (int) $cursor->id;
                }
                $cursor = $cursor->parent_id !== null ? Location::query()->find($cursor->parent_id) : null;
            }
        }

        $areas = $eligibleLocationIds === [] ? collect() : GovernanceArea::query()
            ->where('area_kind', 'community')
            ->where('status', 'active')
            ->whereHas('locations', fn ($query) => $query->whereIn('locations.id', $eligibleLocationIds))
            ->get();

        $activeGroupIds = [];
        foreach ($areas as $area) {
            $group = $this->ensureMembership($area, $user);
            if ($group !== null) {
                $activeGroupIds[] = (int) $group->id;
            }
        }

        $stale = GroupUser::query()
            ->where('user_id', $user->id)
            ->where('status', 1)
            ->whereHas('group.governanceArea', fn ($query) => $query->where('area_kind', 'community'));

        if ($activeGroupIds !== []) {
            $stale->whereNotIn('group_id', $activeGroupIds);
        }

        $stale->update(['status' => 0]);

        return $activeGroupIds;
    }

    public function ensureMembership(GovernanceArea $area, User $user): ?Group
    {
        $location = $area->locations()->where('locations.status', 'active')->first();
        if ($location === null || ! $this->creationPolicy->mayCreateFor($location, $user)) {
            return null;
        }

        return DB::transaction(function () use ($area, $user): Group {
            $group = $this->materializePublicAssembly($area);
            $this->activateMembership($group, $user);

            return $group;
        });
    }

    public function publicAssemblyFor(GovernanceArea $area): ?Group
    {
        if ($area->area_kind !== 'community') {
            return null;
        }

        return Group::query()
            ->where('governance_area_id', $area->id)
            ->where('dimension_key', 'public')
            ->where('dimension_value_key', 'public')
            ->first();
    }

    private function materializePublicAssembly(GovernanceArea $area): Group
    {
        return Group::query()->firstOrCreate(
            [
                'governance_area_id' => $area->id,
                'dimension_key' => 'public',
                'dimension_value_key' => 'public',
            ],
            [
                'name' => 'اجتماع محلی '.($area->localized_names['fa'] ?? $area->canonical_name),
                'group_type' => '0',
                'is_open' => 1,
            ],
        );
    }

    private function activateMembership(Group $group, User $user): void
    {
        GroupUser::withTrashed()->updateOrCreate(
            ['group_id' => $group->id, 'user_id' => $user->id],
            ['role' => 1, 'status' => 1, 'expired' => null, 'deleted_at' => null],
        );
    }
}
