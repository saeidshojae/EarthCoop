<?php

namespace App\Notifications\NajmBahar;

use App\Modules\NajmBahar\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ProjectRevisionRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Project $project,
        public string $revisionNotes
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'project_revision_requested',
            'project_id' => $this->project->id,
            'project_title' => $this->project->title,
            'message' => 'درخواست اصلاح برای پروژه شما ثبت شد.',
            'revision_notes' => $this->revisionNotes,
            'url' => route('najm-bahar.projects.edit', $this->project),
        ];
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
