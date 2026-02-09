<?php

namespace App\Notifications\NajmBahar;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use App\Helpers\BaharMoney;

class TransactionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $type, // 'in' or 'out'
        public int $amount,
        public string $description,
        public ?int $transactionId = null,
        public ?int $balance = null
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
            ? 'دریافت وجه' 
            : 'ارسال وجه';
        
        $message = $isIncoming
            ? "مبلغ " . BaharMoney::format($this->amount) . " به حساب شما واریز شد."
            : "مبلغ " . BaharMoney::format($this->amount) . " از حساب شما کسر شد.";
        
        if ($this->description) {
            $message .= " توضیحات: " . $this->description;
        }
        
        if ($this->balance !== null) {
            $message .= " موجودی فعلی: " . BaharMoney::format($this->balance);
        }

        return [
            'title' => $title,
            'message' => $message,
            'url' => route('najm-bahar.dashboard'),
            'type' => $isIncoming ? 'success' : 'info',
            'icon' => $isIncoming ? 'fa-arrow-down' : 'fa-arrow-up',
            'context' => [
                'transaction_id' => $this->transactionId,
                'amount' => $this->amount,
                'type' => $this->type,
                'balance' => $this->balance,
            ],
        ];
    }
}

