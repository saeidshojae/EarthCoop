<?php

namespace App\Notifications\NajmBahar;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use App\Helpers\BaharMoney;

class ScheduledTransactionExecutedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $amount,
        public string $description,
        public ?int $transactionId = null
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
            'title' => 'تراکنش زمان‌بندی شده اجرا شد',
            'message' => "تراکنش زمان‌بندی شده شما به مبلغ " . BaharMoney::format($this->amount) . " با موفقیت اجرا شد." . ($this->description ? " توضیحات: " . $this->description : ""),
            'url' => route('najm-bahar.dashboard'),
            'type' => 'success',
            'icon' => 'fa-check-circle',
            'context' => [
                'transaction_id' => $this->transactionId,
                'amount' => $this->amount,
            ],
        ];
    }
}

