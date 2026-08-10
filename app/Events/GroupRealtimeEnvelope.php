<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GroupRealtimeEnvelope implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public array $envelope) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('group.' . (int) $this->envelope['group_id'])];
    }

    public function broadcastAs(): string
    {
        return 'group.realtime.event';
    }

    public function broadcastWith(): array
    {
        return $this->envelope;
    }
}
