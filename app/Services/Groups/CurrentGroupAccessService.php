<?php

namespace App\Services\Groups;

use App\Models\Group;
use App\Models\User;
use App\Services\Membership\MembershipEngine;
use Illuminate\Support\Collection;

/**
 * Read-only authorization boundary for current group memberships.
 * Never reconciles pivots, materializes groups or writes residence state.
 */
final class CurrentGroupAccessService
{
    public function groupsFor(User $user): Collection
    {
        $groups = $user->groups()
            ->wherePivot('status', 1)
            ->where(function ($query): void {
                $query->whereNull('group_user.expired')
                    ->orWhere('group_user.expired', '>', now());
            })
            ->get();

        if (! (bool) config('location-governance.groups_enabled', false)) {
            return $groups;
        }

        $intents = app(MembershipEngine::class)->resolve($user, false)->materializableIntents;
        $allowed = $intents->map(fn ($intent): string => implode('|', [
            (int) $intent->governanceAreaId,
            (string) $intent->dimensionKey,
            (string) $intent->valueKey,
        ]))->flip();

        return $groups->filter(static function (Group $group) use ($allowed): bool {
            if ((int) $group->location_level === 10) {
                return true;
            }

            if ($group->governance_area_id === null) {
                return false;
            }

            return $allowed->has(implode('|', [
                (int) $group->governance_area_id,
                (string) $group->dimension_key,
                (string) $group->dimension_value_key,
            ]));
        })->values();
    }

    public function contains(User $user, int $groupId): bool
    {
        return $groupId > 0 && $this->groupsFor($user)
            ->contains(fn (Group $group): bool => (int) $group->id === $groupId);
    }

    public function idsFor(User $user): array
    {
        return $this->groupsFor($user)->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }
}
