<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ChatRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $chatRequestId,
        public string $senderName,
        public string $message,
        public ?string $requestToGroup = null
    ) {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        // Broadcast if enabled
        if (config('broadcasting.default') !== 'null') {
            $channels[] = 'broadcast';
        }
        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage(array_merge($this->payload(), [
            'broadcasted_at' => now()->toIso8601String(),
        ]));
    }

    protected function payload(): array
    {
        return [
            'chat_request_id' => $this->chatRequestId,
            'sender_name' => $this->senderName,
            'message' => $this->message,
            'request_to_group' => $this->requestToGroup,
            'url' => route('chat-requests.index'),
            'type' => 'chat_request',
        ];
    }
}