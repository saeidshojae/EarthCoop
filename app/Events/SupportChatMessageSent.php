<?php

namespace App\Events;

use App\Models\SupportChat;
use App\Models\SupportChatMessage;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupportChatMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SupportChatMessage $message,
        public SupportChat $chat,
        public User $sender
    ) {
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        $channels = [
            new PrivateChannel('support-chat.' . $this->chat->id),
            new PrivateChannel('user.' . $this->chat->user_id),
        ];
        
        // اگر پشتیبان دارد، به channel پشتیبان هم broadcast کن
        if ($this->chat->agent_id) {
            $channels[] = new PrivateChannel('user.' . $this->chat->agent_id);
        }
        
        return $channels;
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'message.sent';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'id' => $this->message->id,
            'support_chat_id' => $this->message->support_chat_id,
            'user_id' => $this->message->user_id,
            'type' => $this->message->type,
            'message' => $this->message->message,
            'attachments' => $this->message->attachments,
            'created_at' => $this->message->created_at->toIso8601String(),
            'user' => [
                'id' => $this->sender->id,
                'name' => $this->sender->fullName(),
            ],
            'chat' => [
                'id' => $this->chat->id,
                'status' => $this->chat->status,
                'agent_id' => $this->chat->agent_id,
            ],
        ];
    }
}
