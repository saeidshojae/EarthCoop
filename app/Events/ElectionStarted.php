<?php

namespace App\Events;

use App\Models\Election;
use App\Models\Group;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ElectionStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Election $election,
        public Group $group
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('group.' . $this->group->id)];
    }

    public function broadcastAs(): string
    {
        return 'group.election.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'group_id' => (int) $this->group->id,
            'actor_id' => null,
            'action' => 'election_started',
            'payload' => [
                'election_id' => (int) $this->election->id,
                'starts_at' => $this->election->starts_at ? (string) $this->election->starts_at : null,
                'ends_at' => $this->election->ends_at ? (string) $this->election->ends_at : null,
                'is_closed' => (bool) $this->election->is_closed,
            ],
        ];
    }
}

