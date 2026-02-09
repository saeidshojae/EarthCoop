<?php

namespace App\Events;

use App\Models\Election;
use App\Models\Group;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ElectionStarted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Election $election,
        public Group $group
    ) {
    }
}

