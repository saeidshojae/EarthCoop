<?php

namespace App\Services\Projects;

use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use App\Notifications\NajmBahar\ProjectAssigned;
use App\Services\Communication\CommunicationDispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

final class ProjectAssignmentNotificationService
{
    public function __construct(
        private readonly CommunicationDispatcher $communications,
    ) {
    }

    /** @param iterable<int,User> $recipients */
    public function send(Project $project, iterable $recipients, ?string $assignmentNote = null): void
    {
        $users = collect($recipients)
            ->filter(fn ($recipient): bool => $recipient instanceof User)
            ->unique('id')
            ->values();

        if ($users->isEmpty()) {
            return;
        }

        // Keep in-app/broadcast notifications on Laravel Notification.
        Notification::send($users, new ProjectAssigned($project, $assignmentNote));

        $assignmentKey = $project->assigned_at?->format('YmdHis')
            ?? (string) ($project->updated_at?->format('YmdHis') ?? now()->format('YmdHis'));

        foreach ($users as $recipient) {
            try {
                $recipientName = trim($recipient->displayName()) ?: (string) $recipient->email;
                $this->communications->dispatch(
                    'najm_bahar.project_assigned',
                    ['type' => 'najm_bahar.project_assignment', 'id' => (string) $project->id],
                    [$recipient],
                    [
                        'recipient_name' => $recipientName,
                        'project_title' => (string) $project->title,
                        'category' => (string) ($project->categoryPath ?? 'نامشخص'),
                        'required_capital' => $project->required_capital !== null
                            ? number_format((float) $project->required_capital).' تومان'
                            : 'نامشخص',
                        'assignment_note' => trim((string) $assignmentNote) ?: 'بدون توضیح',
                        'project_url' => route('admin.najm-bahar.projects.show', $project),
                    ],
                    [
                        'priority' => 2,
                        'deduplication_key' => 'najm_bahar.project_assigned:'.$project->id.':'.$recipient->id.':'.$assignmentKey,
                    ],
                );
            } catch (Throwable $exception) {
                // Assignment itself must remain valid even if communication setup is temporarily unavailable.
                Log::error('Failed to queue canonical project assignment email', [
                    'project_id' => $project->id,
                    'recipient_id' => $recipient->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
