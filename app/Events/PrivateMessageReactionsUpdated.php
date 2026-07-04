<?php

namespace App\Events;

use App\Models\PrivateConversation;
use App\Models\PrivateMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrivateMessageReactionsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PrivateMessage $message,
        public PrivateConversation $conversation,
        public array $reactions
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('private-chat.' . $this->conversation->id)];
    }

    public function broadcastAs(): string
    {
        return 'private-message.reactions.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->message->id,
            'reactions' => $this->reactions,
        ];
    }
}
