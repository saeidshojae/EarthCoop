<?php

namespace App\Policies\Concerns;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;

trait ResolvesGroupMembership
{
    private function membership(User $user, Group $group): ?GroupUser
    {
        return GroupUser::query()
            ->where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->where('status', 1)
            ->where(function ($query) {
                $query->whereNull('expired')->orWhere('expired', 0)->orWhere('expired', '>', now());
            })
            ->first();
    }

    private function isAdministrator(User $user): bool
    {
        return (bool) $user->is_admin || $user->hasRole('super-admin');
    }

    private function canModerateGroup(User $user, Group $group): bool
    {
        if ($this->isAdministrator($user)) {
            return true;
        }

        return (int) optional($this->membership($user, $group))->role === 3;
    }
}
