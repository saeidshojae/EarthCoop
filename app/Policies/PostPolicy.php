<?php

namespace App\Policies;

use App\Models\Blog;
use App\Models\User;
use App\Policies\Concerns\ResolvesGroupMembership;

class PostPolicy
{
    use ResolvesGroupMembership;

    public function view(User $user, Blog $post): bool
    {
        return $post->group !== null
            && ($this->isAdministrator($user) || $this->membership($user, $post->group) !== null);
    }

    public function update(User $user, Blog $post): bool
    {
        return $this->view($user, $post)
            && ((int) $post->user_id === (int) $user->id || $this->canModerateGroup($user, $post->group));
    }

    public function delete(User $user, Blog $post): bool
    {
        return $this->update($user, $post);
    }
}
