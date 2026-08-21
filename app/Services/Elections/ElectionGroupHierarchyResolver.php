<?php

namespace App\Services\Elections;

use App\Models\Group;
use App\Models\User;
use App\Services\GroupService;
use RuntimeException;

class ElectionGroupHierarchyResolver
{
    public function __construct(private readonly GroupService $groups) {}

    /**
     * Resolve the exact corresponding group one geographic level above the
     * source group for this member, preserving the complete group subtype.
     * Global is the terminal level and therefore has no represented group.
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

        $query = Group::query()
            ->where('group_type', $source->group_type)
            ->where('location_level', $target['level']);

        if ($target['id'] === null) {
            $query->whereNull('address_id');
        } else {
            $query->where('address_id', $target['id']);
        }

        foreach (['specialty_id', 'experience_id', 'age_group_id', 'gender'] as $field) {
            $value = $source->{$field};
            $value === null ? $query->whereNull($field) : $query->where($field, $value);
        }

        $group = $query->first();
        if ($group === null) {
            throw new RuntimeException(
                "Corresponding higher-level group is missing for source group [{$source->id}] at [{$target['level']}]."
            );
        }

        return $group;
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
