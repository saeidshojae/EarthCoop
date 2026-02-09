<?php

namespace App\Events;

use App\Models\Group;
use App\Models\Message;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserMentioned
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(
        public User $mentionedUser,
        public Message $message,
        public Group $group,
        public User $mentioner
    ) {
        //
    }
}
