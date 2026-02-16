<?php

namespace App\Notifications\NajmBahar;

use App\Modules\NajmBahar\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjectAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Project $project,
        public ?string $assignmentNote = null
    ) {}

    /**
     * کانال‌های ارسال اعلان
     */
    public function via($notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    /**
     * اعلان ایمیل
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->greeting('سلام ' . $notifiable->name)
            ->line('یک پروژه برای بررسی‌ و ارزیابی توسط شما منتخب شده است.')
            ->line('**نام پروژه:** ' . $this->project->title)
            ->line('**دسته‌بندی:** ' . ($this->project->categoryPath ?? 'نامشخص'))
            ->line('**سرمایه مورد نیاز:** ' . number_format($this->project->required_capital) . ' تومان')
            ->when($this->assignmentNote, function ($message) {
                return $message->line('**توضیحات ارجاع دهنده:** ' . $this->assignmentNote);
            })
            ->action('مشاهده جزئیات پروژه', route('admin.najm-bahar.projects.show', $this->project))
            ->line('لطفاً نظر و ارزیابی خود را در سایستم ثبت کنید.')
            ->salutation('با تشکر،' . "\n" . config('app.name'));
    }

    /**
     * اعلان دیتابیس
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'project_assigned',
            'project_id' => $this->project->id,
            'project_title' => $this->project->title,
            'message' => 'پروژه "' . $this->project->title . '" برای بررسی به شما ارجاع شد.',
            'assignment_note' => $this->assignmentNote,
            'url' => route('admin.najm-bahar.projects.show', $this->project),
        ];
    }

    /**
     * اعلان Broadcast
     */
    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
