<?php

namespace App\Events;

use App\Models\Blog;
use App\Models\Group;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BlogCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Blog $blog,
        public Group $group,
        public User $author
    ) {
    }
}

