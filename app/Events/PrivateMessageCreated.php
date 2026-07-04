<?php

namespace App\Events;

use App\Models\PrivateConversation;
use App\Models\PrivateMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrivateMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PrivateMessage $message,
        public PrivateConversation $conversation
    ) {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('private-chat.' . $this->conversation->id)];
    }

    public function broadcastAs(): string
    {
        return 'private-message.created';
    }

    public function broadcastWith(): array
    {
        $sender = $this->message->sender;

        return [
            'message' => [
                'id' => $this->message->id,
                'message' => $this->message->message,
                'sender' => [
                    'id' => $sender->id,
                    'name' => trim(($sender->first_name ?? '') . ' ' . ($sender->last_name ?? '')),
                    'avatar' => $sender->avatar ?? null,
                ],
                'created_at' => $this->message->created_at->toDateTimeString(),
                'reaction_summary' => [],
            ],
            'conversation_id' => $this->conversation->id,
        ];
    }
}
