<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupMessageUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $groupId,
        public string $action,
        public array $payload = [],
        public ?int $actorId = null
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('group.' . $this->groupId)];
    }

    public function broadcastAs(): string
    {
        return 'group.message.updated';
    }

    public function broadcastWhen(): bool
    {
        return (bool) config('group-chat.features.realtime_reactions', false);
    }

    public function broadcastWith(): array
    {
        return [
            'group_id' => $this->groupId,
            'action' => $this->action,
            'actor_id' => $this->actorId,
            'payload' => $this->payload,
        ];
    }
}

