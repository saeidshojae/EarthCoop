<?php

namespace App\Services\Elections;

use App\Models\Address;
use App\Models\Group;
use App\Models\User;
use App\Services\GroupService;
use Illuminate\Support\Collection;
use RuntimeException;

class ElectionGroupHierarchyResolver
{
    private const LEVEL_COLUMNS = [
        'global' => null,
        'continent' => 'continent_id',
        'country' => 'country_id',
        'province' => 'province_id',
        'county' => 'county_id',
        'section' => 'section_id',
        'city' => 'city_id',
        'rural' => 'rural_id',
        'village' => 'village_id',
        'region' => 'region_id',
        'neighborhood' => 'neighborhood_id',
        'street' => 'street_id',
        'alley' => 'alley_id',
    ];

    public function __construct(private readonly GroupService $groups) {}

    /**
     * Resolve the exact corresponding group one *actual user-path* level above
     * the source group, preserving the complete group subtype. Missing optional
     * geography (for example region in a one-region city) is naturally skipped.
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
     * Return the chain of higher scopes that must inherit the same appointment
     * because each parent currently has exactly one effective lower electoral
     * constituency. This is generic across alley/street/neighborhood/region,
     * village/rural/city/section/county/... and is not city-specific.
     *
     * "Effective" deliberately means a lower group on the same governance
     * track with at least one active non-system member represented by current
     * address data. If a second constituency later becomes effective, the next
     * election cycle stops compressing that parent automatically.
     *
     * @return array<int, Group>
     */
    public function compressionChain(Group $source, User $user): array
    {
        $chain = [];
        $current = $source;

        while (($parent = $this->higherGroup($current, $user)) !== null) {
            if (! $this->isSoleEffectiveConstituency($current, $parent)) {
                break;
            }

            $chain[] = $parent;
            $current = $parent;
        }

        return $chain;
    }

    /**
     * Resolve the first higher scope that still needs genuine representation
     * rather than inheriting the lower appointment. Null means global/top end.
     */
    public function nextElectoralParent(Group $source, User $user): ?Group
    {
        $chain = $this->compressionChain($source, $user);
        $highest = $chain === [] ? $source : $chain[array_key_last($chain)];

        return $this->higherGroup($highest, $user);
    }

    public function isSoleEffectiveConstituency(Group $child, Group $parent): bool
    {
        if (! $this->sameTrack($child, $parent)) {
            return false;
        }

        $ids = $this->effectiveConstituencyIds($parent, $child);
        if ($ids->count() !== 1) {
            return false;
        }

        return $child->address_id === null
            ? false
            : (int) $ids->first() === (int) $child->address_id;
    }

    public function effectiveConstituencyCount(Group $parent, Group $childPrototype): int
    {
        return $this->effectiveConstituencyIds($parent, $childPrototype)->count();
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

    private function effectiveConstituencyIds(Group $parent, Group $childPrototype): Collection
    {
        $parentColumn = self::LEVEL_COLUMNS[$parent->location_level] ?? '__unsupported__';
        $childColumn = self::LEVEL_COLUMNS[$childPrototype->location_level] ?? '__unsupported__';

        if ($parentColumn === '__unsupported__' || $childColumn === '__unsupported__' || $childColumn === null) {
            throw new RuntimeException('Unsupported election geography level while resolving effective constituencies.');
        }

        $addresses = Address::query()->where('status', 1)->whereNotNull($childColumn);
        if ($parentColumn !== null) {
            if ($parent->address_id === null) {
                return collect();
            }
            $addresses->where($parentColumn, $parent->address_id);
        }

        $geographicIds = $addresses->distinct()->pluck($childColumn)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($geographicIds->isEmpty()) {
            return collect();
        }

        $groups = Group::query()
            ->where('group_type', $childPrototype->group_type)
            ->where('location_level', $childPrototype->location_level)
            ->whereIn('address_id', $geographicIds);

        foreach (['specialty_id', 'experience_id', 'age_group_id', 'gender'] as $field) {
            $value = $childPrototype->{$field};
            $value === null ? $groups->whereNull($field) : $groups->where($field, $value);
        }

        $groups->whereHas('groupUser', function ($query): void {
            $query->where('status', 1)
                ->where('role', '!=', 4)
                ->whereHas('user', fn ($user) => $user->where('is_system', false));
        });

        return $groups->distinct()->pluck('address_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();
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
