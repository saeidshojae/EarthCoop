<?php

namespace App\Notifications\NajmBahar;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use App\Helpers\BaharMoney;

class LargeTransactionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $type, // 'in' or 'out'
        public int $amount,
        public string $description,
        public int $threshold,
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
        $isIncoming = $this->type === 'in';
        $title = $isIncoming 
            ? 'هشدار: تراکنش بزرگ (ورودی)' 
            : 'هشدار: تراکنش بزرگ (خروجی)';
        
        $message = "یک تراکنش " . ($isIncoming ? "ورودی" : "خروجی") . " بزرگ به مبلغ " . BaharMoney::format($this->amount) . " انجام شد.";
        
        if ($this->description) {
            $message .= " توضیحات: " . $this->description;
        }
        
        $message .= " (آستانه: " . BaharMoney::format($this->threshold) . ")";

        return [
            'title' => $title,
            'message' => $message,
            'url' => route('najm-bahar.dashboard'),
            'type' => 'warning',
            'icon' => 'fa-shield-alt',
            'context' => [
                'transaction_id' => $this->transactionId,
                'amount' => $this->amount,
                'type' => $this->type,
                'threshold' => $this->threshold,
            ],
        ];
    }
}

