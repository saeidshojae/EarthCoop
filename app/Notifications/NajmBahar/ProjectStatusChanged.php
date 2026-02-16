<?php

namespace App\Notifications\NajmBahar;

use App\Modules\NajmBahar\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjectStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Project $project,
        public string $newStatus,
        public ?string $comment = null
    ) {}

    /**
     * کانال‌های ارسال اعلان
     */
    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * اعلان دیتابیس
     */
    public function toArray($notifiable): array
    {
        $messages = [
            'approved' => 'پروژه شما تایید شد و آماده جذب سرمایه است.',
            'rejected' => 'پروژه شما رد شد.',
            'under_review' => 'بررسی پروژه شما آغاز شد.',
            'archived' => 'پروژه شما بایگانی شد.',
        ];

        return [
            'type' => 'project_status_changed',
            'project_id' => $this->project->id,
            'project_title' => $this->project->title,
            'status' => $this->newStatus,
            'message' => $messages[$this->newStatus] ?? 'وضعیت پروژه شما تغییر کرد.',
            'comment' => $this->comment,
            'url' => route('najm-bahar.projects.show', $this->project),
        ];
    }

    /**
     * اعلان Broadcast
     */
    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /**
     * اعلان ایمیل (اختیاری)
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('تغییر وضعیت پروژه: ' . $this->project->title)
            ->line('وضعیت پروژه «' . $this->project->title . '» تغییر کرد.')
            ->line('وضعیت جدید: ' . $this->newStatus)
            ->when($this->comment, function ($mail) {
                return $mail->line('یادداشت: ' . $this->comment);
            })
            ->action('مشاهده پروژه', route('najm-bahar.projects.show', $this->project));
    }
}
