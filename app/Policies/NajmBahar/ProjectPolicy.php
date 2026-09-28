<?php

namespace App\Policies\NajmBahar;

use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use App\Services\Actors\ActorOperation;
use App\Services\Actors\OwnerRepresentationService;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProjectPolicy
{
    use HandlesAuthorization;

    public function __construct(
        private readonly OwnerRepresentationService $owners,
    ) {}

    public function view(User $user, Project $project): bool
    {
        if ($this->isRepresentedOwner($user, $project)) {
            return true;
        }

        if ($project->status === 'approved' && $project->project_visibility === 'public') {
            return true;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return false;
    }

    public function update(User $user, Project $project): bool
    {
        return $this->isRepresentedOwner($user, $project)
            && in_array($project->status, ['draft', 'rejected'], true);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->isRepresentedOwner($user, $project)
            && $project->status === 'draft';
    }

    private function isRepresentedOwner(User $user, Project $project): bool
    {
        if (! is_string($project->owner_type) || $project->owner_type === '' || $project->owner_id === null) {
            return false;
        }

        return $this->owners->allows(
            $user,
            $project->owner_type,
            $project->owner_id,
            ActorOperation::ProjectOwner,
        );
    }
}
