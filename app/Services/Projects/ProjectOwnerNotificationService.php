<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Models\Group;
use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use App\Services\Groups\EffectiveGroupMembershipService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;

final class ProjectOwnerNotificationService
{
    public function __construct(private readonly EffectiveGroupMembershipService $memberships)
    {
    }

    public function recipients(Project $project): Collection
    {
        if ($project->owner_id === null || ! is_string($project->owner_type) || $project->owner_type === '') {
            return collect();
        }

        if ($project->owner_type === User::class) {
            $user = User::query()->find($project->owner_id);

            return $user instanceof User ? collect([$user]) : collect();
        }

        if ($project->owner_type === Group::class) {
            $group = Group::query()->find($project->owner_id);

            return $group instanceof Group
                ? $this->memberships->currentManagers($group)
                : collect();
        }

        return collect();
    }

    public function send(Project $project, Notification $notification): void
    {
        $recipients = $this->recipients($project);
        if ($recipients->isEmpty()) {
            return;
        }

        NotificationFacade::send($recipients, $notification);
    }
}
