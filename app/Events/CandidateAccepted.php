<?php

namespace App\Events;

use App\Models\Candidate;
use App\Models\Election;
use App\Models\Group;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CandidateAccepted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Candidate $candidate,
        public Election $election,
        public Group $group,
        public User $user
    ) {
    }
}

