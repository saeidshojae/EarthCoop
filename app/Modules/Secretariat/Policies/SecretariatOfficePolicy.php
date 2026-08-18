<?php

namespace App\Modules\Secretariat\Policies;

use App\Models\Group;
use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Policies\Concerns\ResolvesGroupMembership;
use App\Policies\NajmBahar\ProjectPolicy;

class SecretariatOfficePolicy
{
    use ResolvesGroupMembership;

    public function view(User $user, SecretariatOffice $office): bool
    {
        if ($this->isAdministrator($user)) {
            return true;
        }

        if ($office->scope_type === 'group') {
            $group = $this->groupScope($office);
            return $group !== null && $this->membership($user, $group) !== null;
        }

        if ($office->scope_type === 'najm_bahar_project') {
            $project = $this->projectScope($office);
            return $project !== null && app(ProjectPolicy::class)->view($user, $project);
        }

        // Central, legal-entity, committee and unknown scopes stay default-deny
        // for non-admins until their source domains define explicit authority.
        return false;
    }

    public function manage(User $user, SecretariatOffice $office): bool
    {
        if ($this->isAdministrator($user)) {
            return true;
        }

        if ($office->scope_type === 'group') {
            $group = $this->groupScope($office);
            return $group !== null && (int) optional($this->membership($user, $group))->role === 3;
        }

        if ($office->scope_type === 'najm_bahar_project') {
            return $this->isUserProjectOwner($user, $office);
        }

        return false;
    }

    public function inspect(User $user, SecretariatOffice $office): bool
    {
        if ($this->isAdministrator($user)) {
            return true;
        }

        if ($office->scope_type === 'group') {
            $group = $this->groupScope($office);
            return $group !== null && in_array((int) optional($this->membership($user, $group))->role, [2, 3], true);
        }

        if ($office->scope_type === 'najm_bahar_project') {
            return $this->isUserProjectOwner($user, $office);
        }

        return false;
    }

    private function groupScope(SecretariatOffice $office): ?Group
    {
        if ($office->scope_type !== 'group' || $office->scope_id === null) {
            return null;
        }

        return Group::query()->find($office->scope_id);
    }

    private function projectScope(SecretariatOffice $office): ?Project
    {
        if ($office->scope_type !== 'najm_bahar_project' || $office->scope_id === null) {
            return null;
        }

        return Project::query()->find($office->scope_id);
    }

    private function isUserProjectOwner(User $user, SecretariatOffice $office): bool
    {
        $project = $this->projectScope($office);
        return $project !== null
            && $project->owner_type === User::class
            && (int) $project->owner_id === (int) $user->id;
    }
}
