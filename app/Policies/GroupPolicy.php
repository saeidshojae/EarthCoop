<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;
use App\Policies\Concerns\ResolvesGroupMembership;

class GroupPolicy
{
    use ResolvesGroupMembership;

    public function view(User $user, Group $group): bool
    {
        return $this->isAdministrator($user) || $this->membership($user, $group) !== null;
    }

    public function participate(User $user, Group $group): bool
    {
        if ($this->isAdministrator($user)) {
            return true;
        }

        $membership = $this->membership($user, $group);
        if ($membership === null) {
            return false;
        }

        return (bool) $group->is_open
            || in_array((int) $membership->role, [2, 3], true)
            || (bool) $membership->session_write_allowed;
    }

    public function manageSession(User $user, Group $group): bool
    {
        if ($this->isAdministrator($user)) {
            return true;
        }

        return in_array((int) optional($this->membership($user, $group))->role, [2, 3], true);
    }

    public function moderate(User $user, Group $group): bool
    {
        return $this->canModerateGroup($user, $group);
    }

    public function manage(User $user, Group $group): bool
    {
        return $this->canModerateGroup($user, $group);
    }
}
