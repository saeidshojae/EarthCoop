<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;
use App\Policies\Concerns\ResolvesGroupMembership;

class CommentPolicy
{
    use ResolvesGroupMembership;

    public function view(User $user, Comment $comment): bool
    {
        return $comment->blog?->group !== null
            && ($this->isAdministrator($user) || $this->membership($user, $comment->blog->group) !== null);
    }

    public function update(User $user, Comment $comment): bool
    {
        return $this->view($user, $comment)
            && ((int) $comment->user_id === (int) $user->id || $this->canModerateGroup($user, $comment->blog->group));
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $this->update($user, $comment);
    }
}
