<?php

namespace App\Services\Elections;

use App\Models\Group;
use App\Models\User;
use App\Services\GroupService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ElectionGroupHierarchyResolver
{
    /**
     * Structural child table and its parent foreign-key for the canonical
     * geographic hierarchy. `neighborhood.parent_id` intentionally supports
     * either region/village or a directly higher city/rural scope when an
     * optional intermediate layer does not exist in the user's path.
     */
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

    /**
     * Resolve the exact corresponding group one actual user-path level above
     * the source group, preserving the complete group subtype. Optional absent
     * geography is naturally skipped by GroupService::getLocationLevels().
     */
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

    /**
     * Higher scopes that inherit the same elected responsibility because every
     * parent is structurally a one-constituency scope. This rule is geographic,
     * not population-driven: low EarthCoop adoption can never make a local
     * manager accidentally inherit a national/global office.
     *
     * @return array<int, Group>
     */
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

    /** First higher scope that still requires genuine multi-constituency representation. */
    public function nextElectoralParent(Group $source, User $user): ?Group
    {
        $chain = $this->compressionChain($source, $user);
        $highest = $chain === [] ? $source : $chain[array_key_last($chain)];

        return $this->higherGroup($highest, $user);
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
     * Count configured geographic constituencies, irrespective of current member
     * count. Under a `section`, city and rural are parallel child branches and
     * must be counted together to avoid false compression.
     */
    public function structuralConstituencyCount(Group $parent, string $childLevel): int
    {
        if ($parent->location_level === 'section' && in_array($childLevel, ['city', 'rural'], true)) {
            $parentId = (int) $parent->address_id;
            return (int) DB::table('cities')->where('district_id', $parentId)->count()
                + (int) DB::table('rurals')->where('district_id', $parentId)->count();
        }

        $mapping = self::CHILD_TO_PARENT[$childLevel] ?? null;
        if ($mapping === null) {
            throw new RuntimeException("Unsupported structural election child level [{$childLevel}].");
        }

        [$table, $parentColumn] = $mapping;
        $query = DB::table($table);

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

    /** Smaller index means a higher geographic seat. */
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
        if ((string) $left->group_type !== (string) $right->group_type) {
            return false;
        }

        foreach (['specialty_id', 'experience_id', 'age_group_id', 'gender'] as $field) {
            if (($left->{$field} ?? null) !== ($right->{$field} ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function childBelongsToParent(Group $child, Group $parent): bool
    {
        $mapping = self::CHILD_TO_PARENT[$child->location_level] ?? null;
        if ($mapping === null || $child->address_id === null) {
            return false;
        }

        [$table, $parentColumn] = $mapping;
        $query = DB::table($table)->where('id', $child->address_id);

        if ($parent->location_level === 'global') {
            return $child->location_level === 'continent' && $query->exists();
        }

        if ($parentColumn === null || $parent->address_id === null) {
            return false;
        }

        return $query->where($parentColumn, $parent->address_id)->exists();
    }

    private function matchingGroup(Group $source, string $level, ?int $addressId): Group
    {
        $query = Group::query()
            ->where('group_type', $source->group_type)
            ->where('location_level', $level);

        $addressId === null ? $query->whereNull('address_id') : $query->where('address_id', $addressId);

        foreach (['specialty_id', 'experience_id', 'age_group_id', 'gender'] as $field) {
            $value = $source->{$field};
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
