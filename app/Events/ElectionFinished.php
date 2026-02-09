<?php

namespace App\Events;

use App\Models\Election;
use App\Models\Group;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ElectionFinished
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Election $election,
        public Group $group,
        public Collection $electedCandidates
    ) {
    }
}

