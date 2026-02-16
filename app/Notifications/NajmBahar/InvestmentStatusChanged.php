<?php

namespace App\Notifications\NajmBahar;

use App\Modules\NajmBahar\Models\Investment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class InvestmentStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Investment $investment,
        public string $newStatus,
        public ?string $notes = null
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray($notifiable): array
    {
        $messages = [
            'paid' => 'پرداخت سرمایه‌گذاری شما تایید شد.',
            'active' => 'سرمایه‌گذاری شما فعال شد.',
            'completed' => 'سرمایه‌گذاری شما تکمیل و سود پرداخت شد.',
            'cancelled' => 'سرمایه‌گذاری شما لغو شد.',
            'refunded' => 'مبلغ سرمایه‌گذاری به شما بازگشت داده شد.',
        ];

        return [
            'type' => 'investment_status_changed',
            'investment_id' => $this->investment->id,
            'project_title' => $this->investment->project->title,
            'amount' => $this->investment->amount,
            'status' => $this->newStatus,
            'message' => $messages[$this->newStatus] ?? 'وضعیت سرمایه‌گذاری شما تغییر کرد.',
            'notes' => $this->notes,
            'url' => route('najm-bahar.investments.show-investment', $this->investment),
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
