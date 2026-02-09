<?php

namespace App\Events;

use App\Models\Group;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupInvitation
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Group $group,
        public User $invitedUser,
        public ?User $inviter = null
    ) {
    }
}

