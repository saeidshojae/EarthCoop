<?php

namespace App\Notifications\NajmBahar;

use App\Modules\NajmBahar\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
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
        return ['database', 'broadcast'];
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
