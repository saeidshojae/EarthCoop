<?php

namespace App\Notifications;

use App\Services\Notifications\NotificationCursor;
use App\Support\Notifications\NotificationLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class GenericNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public ?string $url = null,
        public string $type = 'info',
        public array $context = [],
        public ?NotificationLink $link = null,
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
        $occurredAt = now();
        $eventId = (string) ($this->id ?: Str::uuid());

        return new BroadcastMessage(array_merge($this->payload(), [
            'event_id' => $eventId,
            'stream' => 'notifications',
            'event_type' => 'notification.created',
            'occurred_at' => $occurredAt->toIso8601String(),
            'cursor' => app(NotificationCursor::class)->encode($occurredAt, $eventId),
            'broadcasted_at' => $occurredAt->toIso8601String(),
        ]));
    }

    protected function payload(): array
    {
        $payload = [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'type' => $this->type,
            'context' => $this->context,
        ];

        if ($this->link !== null) {
            $payload['link'] = $this->link->toArray();
        }

        return $payload;
    }
}
