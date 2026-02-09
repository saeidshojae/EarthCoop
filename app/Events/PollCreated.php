<?php

namespace App\Events;

use App\Models\Poll;
use App\Models\Group;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PollCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Poll $poll,
        public Group $group,
        public User $creator
    ) {
    }
}

