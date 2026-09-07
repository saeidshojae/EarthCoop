<?php

namespace App\Services\Elections;

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

    public function __construct(private readonly GroupService $groups) {}

    public function higherGroup(Group $source, User $user): ?Group
    {
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

    /**
     * An election layer is independent unless its approved geographic topology
     * has exactly one effective structural child constituency. A single child
     * means the lower elected office represents the same constituency and its
     * appointment is inherited into this layer instead of running a duplicate
     * election here.
     *
     * Zero children intentionally remains independent: it is the lowest
     * configured geographic layer available for that branch. Population is
     * never consulted by this decision.
     */
    public function isIndependentElectoralLayer(Group $group): bool
    {
        $count = $this->effectiveStructuralChildCount($group);

        return $count === null || $count !== 1;
    }

    /**
     * Return the number of approved direct/effective geographic constituencies
     * under a group. Null denotes a structural leaf (alley).
     *
     * Optional urban/rural layers are folded without relying on current users:
     * - section counts city + rural together;
     * - city counts regions plus neighborhoods attached directly to the city;
     * - rural counts villages plus neighborhoods attached directly to the rural;
     * - region/village count their directly attached neighborhoods.
     */
    public function effectiveStructuralChildCount(Group $parent): ?int
    {
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
        if (! $this->sameTrack($child, $parent) || $child->address_id === null) {
            return false;
        }

        return $this->structuralConstituencyCount($parent, $child->location_level) === 1
            && $this->childBelongsToParent($child, $parent);
    }

    /**
     * Count approved/configured geographic constituencies, irrespective of
     * current EarthCoop population. When a location table exposes `status`, the
     * canonical Geographic API treats only status=1 as usable; election topology
     * follows the same rule so pending admin submissions cannot alter governance.
     */
    public function structuralConstituencyCount(Group $parent, string $childLevel): int
    {
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
        $index = $this->indexFor($group, $this->pathFor($user));
        if ($index === null) {
            throw new RuntimeException("Group [{$group->id}] is not on user [{$user->id}] geographic hierarchy.");
        }

        return $index;
    }

    public function sameTrack(Group $left, Group $right): bool
    {
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
