<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;
use App\Policies\Concerns\ResolvesGroupMembership;

class MessagePolicy
{
    use ResolvesGroupMembership;

    public function view(User $user, Message $message): bool
    {
        return $message->group !== null
            && ($this->isAdministrator($user) || $this->membership($user, $message->group) !== null);
    }

    public function update(User $user, Message $message): bool
    {
        return $this->view($user, $message)
            && ($this->canModerateGroup($user, $message->group)
                || ((int) $message->user_id === (int) $user->id && $this->canParticipateInGroup($user, $message->group)));
    }

    public function delete(User $user, Message $message): bool
    {
        return $this->update($user, $message);
    }
}
