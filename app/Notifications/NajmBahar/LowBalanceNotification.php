<?php

namespace App\Notifications\NajmBahar;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use App\Helpers\BaharMoney;

class LowBalanceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $balance,
        public int $threshold
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
            'title' => 'هشدار موجودی کم',
            'message' => "موجودی حساب شما (" . BaharMoney::format($this->balance) . ") کمتر از آستانه تعیین شده (" . BaharMoney::format($this->threshold) . ") است.",
            'url' => route('najm-bahar.dashboard'),
            'type' => 'warning',
            'icon' => 'fa-exclamation-triangle',
            'context' => [
                'balance' => $this->balance,
                'threshold' => $this->threshold,
            ],
        ];
    }
}

