<?php

namespace App\Events;

use App\Models\ChatRequest;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatRequestToGroup
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ChatRequest $chatRequest,
        public Group $group,
        public User $sender
    ) {}
}

