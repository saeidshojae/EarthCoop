<?php

namespace App\Notifications\NajmBahar;

use App\Modules\NajmBahar\Models\Investment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class NewInvestmentReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Investment $investment
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'new_investment_received',
            'investment_id' => $this->investment->id,
            'project_id' => $this->investment->project_id,
            'project_title' => $this->investment->project->title,
            'amount' => $this->investment->amount,
            'investor_name' => $this->investment->investor->fullName ?? $this->investment->investor->name,
            'message' => 'سرمایه‌گذاری جدیدی در پروژه شما ثبت شد.',
            'url' => route('najm-bahar.projects.show', $this->investment->project),
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
