<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupFeedUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public int $groupId;
    public string $action;
    public array $payload;
    public int $actorId;

    public function __construct(int $groupId, string $action, array $payload = [], int $actorId = 0)
    {
        $this->groupId = $groupId;
        $this->action = $action;
        $this->payload = $payload;
        $this->actorId = $actorId;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel("group.{$this->groupId}")];
    }

    public function broadcastAs(): string
    {
        return 'group.feed.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'group_id' => $this->groupId,
            'action' => $this->action,
            'payload' => $this->payload,
            'actor_id' => $this->actorId,
        ];
    }
}

