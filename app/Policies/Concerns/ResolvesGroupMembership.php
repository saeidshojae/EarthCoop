<?php

namespace App\Policies\Concerns;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\Groups\EffectiveGroupMembershipService;

trait ResolvesGroupMembership
{
    private function membership(User $user, Group $group): ?GroupUser
    {
        return app(EffectiveGroupMembershipService::class)->current($user, $group);
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

    private function canParticipateInGroup(User $user, Group $group): bool
    {
        return $user->can('participate', $group);
    }
}
