<?php

namespace App\Events;

use App\Models\Comment;
use App\Models\Blog;
use App\Models\Group;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommentCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Comment $comment,
        public Blog $blog,
        public Group $group,
        public User $author
    ) {
    }
}

