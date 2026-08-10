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
        return $this->view($user, $group);
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
