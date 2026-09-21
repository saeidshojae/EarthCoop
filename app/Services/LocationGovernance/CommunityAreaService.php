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
                // Creation is an explicit opt-in action. Reusing an existing
                // community through this action must therefore also join the actor.
                $group = $this->materializePublicAssembly($existing);
                $this->activateMembership($group, $actor);

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


    /**
     * Reconcile eligibility only. Community membership is opt-in and is never
     * activated merely because a residence is registered or changed.
     *
     * Active memberships that no longer belong to the user's current residence
     * path are deactivated; eligible memberships already chosen by the user stay
     * untouched.
     *
     * @return array<int>
     */
    public function reconcileMembershipsFor(User $user): array
    {
        $eligibleLocationIds = $this->eligibleLocationIdsFor($user);

        $eligibleGroupIds = $eligibleLocationIds === [] ? [] : Group::query()
            ->where('dimension_key', 'public')
            ->where('dimension_value_key', 'public')
            ->whereHas('governanceArea', function ($query) use ($eligibleLocationIds): void {
                $query->where('area_kind', 'community')
                    ->where('status', 'active')
                    ->whereHas('locations', fn ($locations) => $locations->whereIn('locations.id', $eligibleLocationIds));
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $stale = GroupUser::query()
            ->where('user_id', $user->id)
            ->where('status', 1)
            ->whereHas('group.governanceArea', fn ($query) => $query->where('area_kind', 'community'));

        if ($eligibleGroupIds !== []) {
            $stale->whereNotIn('group_id', $eligibleGroupIds);
        }

        $stale->update(['status' => 0, 'updated_at' => now()]);

        return $eligibleGroupIds;
    }

    public function join(GovernanceArea $area, User $user): Group
    {
        $location = $area->locations()->where('locations.status', 'active')->first();
        if ($area->area_kind !== 'community'
            || $area->status !== 'active'
            || $location === null
            || ! $this->creationPolicy->mayCreateFor($location, $user)) {
            throw new DomainException('User is not eligible to join this community.');
        }

        return DB::transaction(function () use ($area, $user): Group {
            $group = $this->materializePublicAssembly($area);
            $this->activateMembership($group, $user);

            return $group;
        });
    }

    public function leave(GovernanceArea $area, User $user): void
    {
        if ($area->area_kind !== 'community') {
            throw new DomainException('Only local community memberships can be left here.');
        }

        $group = $this->publicAssemblyFor($area);
        if ($group === null) {
            return;
        }

        GroupUser::query()
            ->where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->update(['status' => 0, 'updated_at' => now()]);
    }

    private function eligibleLocationIdsFor(User $user): array
    {
        $residenceId = \App\Models\UserLocationRelationship::query()
            ->where('user_id', $user->id)
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->latest('started_at')
            ->value('location_id');

        if ($residenceId === null) {
            return [];
        }

        $eligibleLocationIds = [];
        $cursor = Location::query()->find($residenceId);
        $visited = [];
        while ($cursor !== null && ! isset($visited[$cursor->id])) {
            $visited[$cursor->id] = true;
            if (in_array($cursor->type?->key, ['street', 'alley', 'complex', 'building'], true)) {
                $eligibleLocationIds[] = (int) $cursor->id;
            }
            $cursor = $cursor->parent_id !== null ? Location::query()->find($cursor->parent_id) : null;
        }

        return $eligibleLocationIds;
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
