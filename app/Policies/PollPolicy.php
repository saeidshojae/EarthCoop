<?php

namespace App\Policies;

use App\Models\Poll;
use App\Models\User;
use App\Policies\Concerns\ResolvesGroupMembership;

class PollPolicy
{
    use ResolvesGroupMembership;

    public function view(User $user, Poll $poll): bool
    {
        return $poll->group !== null
            && ($this->isAdministrator($user) || $this->membership($user, $poll->group) !== null);
    }

    public function vote(User $user, Poll $poll): bool
    {
        return $this->view($user, $poll);
    }

    public function update(User $user, Poll $poll): bool
    {
        return $this->view($user, $poll)
            && ((int) $poll->created_by === (int) $user->id || $this->canModerateGroup($user, $poll->group));
    }

    public function delete(User $user, Poll $poll): bool
    {
        return $this->update($user, $poll);
    }
}
