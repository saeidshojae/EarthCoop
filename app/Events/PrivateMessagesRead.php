<?php

namespace App\Events;

use App\Models\PrivateConversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrivateMessagesRead implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PrivateConversation $conversation,
        public array $messageIds,
        public int $readerId,
        public string $readAt
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('private-chat.' . $this->conversation->id)];
    }

    public function broadcastAs(): string
    {
        return 'private-messages.read';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'message_ids' => array_values($this->messageIds),
            'reader_id' => $this->readerId,
            'read_at' => $this->readAt,
        ];
    }
}
