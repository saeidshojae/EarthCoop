<?php

namespace App\Services\Elections;

use App\Contracts\Governance\OfficialGovernanceTopology;
use App\Models\GovernanceArea;
use App\Models\Group;
use App\Models\User;
use App\Services\GroupService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ElectionGroupHierarchyResolver
{
    private const CHILD_TO_PARENT = [
        'alley' => ['alleies', 'parent_id'],
        'street' => ['streets', 'parent_id'],
        'neighborhood' => ['neighborhoods', 'parent_id'],
        'region' => ['regions', 'parent_id'],
        'village' => ['villages', 'rural_id'],
        'city' => ['cities', 'district_id'],
        'rural' => ['rurals', 'district_id'],
        'section' => ['districts', 'county_id'],
        'county' => ['counties', 'province_id'],
        'province' => ['provinces', 'country_id'],
        'country' => ['countries', 'continent_id'],
        'continent' => ['continents', null],
    ];

    public function __construct(
        private readonly GroupService $groups,
        private readonly OfficialGovernanceTopology $officialTopology,
    ) {}

    public function higherGroup(Group $source, User $user): ?Group
    {
        if ($this->canonicalEnabled()) {
            $area = $this->canonicalAreaFor($source);
            if (! $this->isActiveOfficial($area)) {
                return null;
            }

            $parent = $this->officialTopology->parentOf($area);
            if ($parent === null) {
                return null;
            }

            return $this->matchingCanonicalGroup($source, $parent);
        }

        if ($source->location_level === 'global') {
            return null;
        }

        $path = $this->pathFor($user);
        $sourceIndex = $this->indexFor($source, $path);
        if ($sourceIndex === null) {
            throw new RuntimeException("Source group [{$source->id}] is not on user [{$user->id}] geographic hierarchy.");
        }

        $target = $sourceIndex === 1
            ? ['level' => 'global', 'id' => null]
            : $path[$sourceIndex - 1];

        return $this->matchingGroup($source, $target['level'], $target['id']);
    }

    public function compressionChain(Group $source, User $user): array
    {
        $chain = [];
        $current = $source;

        while (($parent = $this->higherGroup($current, $user)) !== null) {
            if (! $this->isSoleStructuralConstituency($current, $parent)) {
                break;
            }

            $chain[] = $parent;
            $current = $parent;
        }

        return $chain;
    }

    public function nextElectoralParent(Group $source, User $user): ?Group
    {
        $chain = $this->compressionChain($source, $user);
        $highest = $chain === [] ? $source : $chain[array_key_last($chain)];

        return $this->higherGroup($highest, $user);
    }

    public function isIndependentElectoralLayer(Group $group): bool
    {
        $count = $this->effectiveStructuralChildCount($group);

        return $count === null || $count !== 1;
    }

    public function effectiveStructuralChildCount(Group $parent): ?int
    {
        if ($this->canonicalEnabled()) {
            $area = $this->canonicalAreaFor($parent);
            if (! $this->isActiveOfficial($area)) {
                return null;
            }

            return $this->officialTopology->childrenOf($area)->count();
        }

        $parentId = $parent->address_id === null ? null : (int) $parent->address_id;

        return match ($parent->location_level) {
            'global' => $this->approvedCount('continents'),
            'continent' => $this->approvedCount('countries', 'continent_id', $parentId),
            'country' => $this->approvedCount('provinces', 'country_id', $parentId),
            'province' => $this->approvedCount('counties', 'province_id', $parentId),
            'county' => $this->approvedCount('districts', 'county_id', $parentId),
            'section' => $this->approvedCount('cities', 'district_id', $parentId)
                + $this->approvedCount('rurals', 'district_id', $parentId),
            'city' => $this->approvedCount('regions', 'parent_id', $parentId)
                + $this->approvedCount('neighborhoods', 'parent_id', $parentId),
            'rural' => $this->approvedCount('villages', 'rural_id', $parentId)
                + $this->approvedCount('neighborhoods', 'parent_id', $parentId),
            'region', 'village' => $this->approvedCount('neighborhoods', 'parent_id', $parentId),
            'neighborhood' => $this->approvedCount('streets', 'parent_id', $parentId),
            'street' => $this->approvedCount('alleies', 'parent_id', $parentId),
            'alley' => null,
            default => throw new RuntimeException("Unsupported election topology level [{$parent->location_level}]."),
        };
    }

    public function isSoleStructuralConstituency(Group $child, Group $parent): bool
    {
        if ($this->canonicalEnabled()) {
            if (! $this->sameTrack($child, $parent)) {
                return false;
            }

            $childArea = $this->canonicalAreaFor($child);
            $parentArea = $this->canonicalAreaFor($parent);
            if (! $this->isActiveOfficial($childArea) || ! $this->isActiveOfficial($parentArea)) {
                return false;
            }

            return $this->officialTopology->parentOf($childArea)?->id === $parentArea->id
                && $this->effectiveStructuralChildCount($parent) === 1;
        }

        if (! $this->sameTrack($child, $parent) || $child->address_id === null) {
            return false;
        }

        return $this->structuralConstituencyCount($parent, $child->location_level) === 1
            && $this->childBelongsToParent($child, $parent);
    }

    public function structuralConstituencyCount(Group $parent, string $childLevel): int
    {
        if ($this->canonicalEnabled()) {
            return $this->effectiveStructuralChildCount($parent) ?? 0;
        }

        if ($parent->location_level === 'section' && in_array($childLevel, ['city', 'rural'], true)) {
            $parentId = (int) $parent->address_id;
            $cities = $this->approved(DB::table('cities')->where('district_id', $parentId), 'cities');
            $rurals = $this->approved(DB::table('rurals')->where('district_id', $parentId), 'rurals');

            return (int) $cities->count() + (int) $rurals->count();
        }

        $mapping = self::CHILD_TO_PARENT[$childLevel] ?? null;
        if ($mapping === null) {
            throw new RuntimeException("Unsupported structural election child level [{$childLevel}].");
        }

        [$table, $parentColumn] = $mapping;
        $query = $this->approved(DB::table($table), $table);

        if ($parent->location_level === 'global') {
            if ($childLevel !== 'continent') {
                throw new RuntimeException('Only continent can be a direct structural child of global.');
            }
            return (int) $query->count();
        }

        if ($parentColumn === null || $parent->address_id === null) {
            return 0;
        }

        return (int) $query->where($parentColumn, $parent->address_id)->count();
    }

    public function hierarchyIndex(Group $group, User $user): int
    {
        if ($this->canonicalEnabled()) {
            $area = $this->canonicalAreaFor($group);
            if (! $this->isActiveOfficial($area)) {
                throw new RuntimeException("Group [{$group->id}] is not on the active official governance hierarchy.");
            }

            $depth = 0;
            $current = $area;
            while (($parent = $this->officialTopology->parentOf($current)) !== null) {
                $depth++;
                $current = $parent;
            }

            return $depth;
        }

        $index = $this->indexFor($group, $this->pathFor($user));
        if ($index === null) {
            throw new RuntimeException("Group [{$group->id}] is not on user [{$user->id}] geographic hierarchy.");
        }

        return $index;
    }

    public function sameTrack(Group $left, Group $right): bool
    {
        if ($this->canonicalEnabled()) {
            return $this->raw($left, 'dimension_key') === $this->raw($right, 'dimension_key')
                && $this->raw($left, 'dimension_value_key') === $this->raw($right, 'dimension_value_key');
        }

        if ((string) $this->raw($left, 'group_type') !== (string) $this->raw($right, 'group_type')) {
            return false;
        }

        foreach (['specialty_id', 'experience_id', 'age_group_id', 'gender'] as $field) {
            if ($this->raw($left, $field) !== $this->raw($right, $field)) {
                return false;
            }
        }

        return true;
    }

    private function approvedCount(string $table, ?string $parentColumn = null, ?int $parentId = null): int
    {
        $query = $this->approved(DB::table($table), $table);

        if ($parentColumn !== null) {
            if ($parentId === null) {
                return 0;
            }
            $query->where($parentColumn, $parentId);
        }

        return (int) $query->count();
    }

    private function childBelongsToParent(Group $child, Group $parent): bool
    {
        $mapping = self::CHILD_TO_PARENT[$child->location_level] ?? null;
        if ($mapping === null || $child->address_id === null) {
            return false;
        }

        [$table, $parentColumn] = $mapping;
        $query = $this->approved(DB::table($table)->where('id', $child->address_id), $table);

        if ($parent->location_level === 'global') {
            return $child->location_level === 'continent' && $query->exists();
        }

        if ($parentColumn === null || $parent->address_id === null) {
            return false;
        }

        return $query->where($parentColumn, $parent->address_id)->exists();
    }

    private function approved(Builder $query, string $table): Builder
    {
        if (Schema::hasColumn($table, 'status')) {
            $query->where($table.'.status', 1);
        }

        return $query;
    }

    private function matchingGroup(Group $source, string $level, ?int $addressId): Group
    {
        $query = Group::query()
            ->where('group_type', $this->raw($source, 'group_type'))
            ->where('location_level', $level);

        $addressId === null ? $query->whereNull('address_id') : $query->where('address_id', $addressId);

        foreach (['specialty_id', 'experience_id', 'age_group_id', 'gender'] as $field) {
            $value = $this->raw($source, $field);
            $value === null ? $query->whereNull($field) : $query->where($field, $value);
        }

        $group = $query->first();
        if ($group === null) {
            throw new RuntimeException(
                "Corresponding higher-level group is missing for source group [{$source->id}] at [{$level}]."
            );
        }

        return $group;
    }

    private function matchingCanonicalGroup(Group $source, GovernanceArea $area): Group
    {
        $group = Group::query()
            ->where('governance_area_id', $area->id)
            ->where('dimension_key', $this->raw($source, 'dimension_key'))
            ->where('dimension_value_key', $this->raw($source, 'dimension_value_key'))
            ->first();

        if ($group === null) {
            throw new RuntimeException(
                "Corresponding official governance group is missing for source group [{$source->id}] at area [{$area->id}]."
            );
        }

        return $group;
    }

    private function canonicalAreaFor(Group $group): ?GovernanceArea
    {
        if ($group->relationLoaded('governanceArea')) {
            $area = $group->getRelation('governanceArea');
            return $area instanceof GovernanceArea ? $area : null;
        }

        $areaId = $this->raw($group, 'governance_area_id');
        return $areaId === null ? null : GovernanceArea::query()->find((int) $areaId);
    }

    private function isActiveOfficial(?GovernanceArea $area): bool
    {
        return $area !== null && $area->area_kind === 'official' && $area->status === 'active';
    }

    private function canonicalEnabled(): bool
    {
        return (bool) config('location-governance.elections_enabled', false);
    }

    private function raw(Group $group, string $field): mixed
    {
        $attributes = $group->getAttributes();
        return $attributes[$field] ?? null;
    }

    private function pathFor(User $user): array
    {
        $path = [['level' => 'global', 'id' => null]];
        foreach ($this->groups->getLocationLevels($user) as $location) {
            $path[] = ['level' => $location['level'], 'id' => (int) $location['id']];
        }

        return $path;
    }

    private function indexFor(Group $group, array $path): ?int
    {
        foreach ($path as $index => $location) {
            if ($location['level'] !== $group->location_level) {
                continue;
            }

            if ($location['id'] === null && $group->address_id === null) {
                return $index;
            }

            if ($location['id'] !== null && (int) $group->address_id === (int) $location['id']) {
                return $index;
            }
        }

        return null;
    }
}
