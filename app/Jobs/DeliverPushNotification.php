<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Push\PushNotificationDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeliverPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $userId,
        public readonly array $payload,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(PushNotificationDispatcher $dispatcher): void
    {
        $user = User::query()->find($this->userId);
        if (! $user) {
            return;
        }

        $dispatcher->dispatch($user, $this->payload);
    }
}
