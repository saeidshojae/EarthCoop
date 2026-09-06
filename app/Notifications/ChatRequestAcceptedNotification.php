<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ChatRequestAcceptedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $chatRequestId,
        public int $conversationId,
        public string $acceptedByName
    ) {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

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
            'conversation_id' => $this->conversationId,
            'accepted_by_name' => $this->acceptedByName,
            'message' => $this->acceptedByName . ' درخواست گفتگوی خصوصی شما را پذیرفت.',
            'url' => route('private-chats.show', $this->conversationId),
            'type' => 'chat_request_accepted',
        ];
    }
}
