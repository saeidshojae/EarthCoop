<?php

namespace App\Modules\Secretariat\Policies;

use App\Models\Group;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatOffice;
use App\Policies\Concerns\ResolvesGroupMembership;

class SecretariatOfficePolicy
{
    use ResolvesGroupMembership;

    public function view(User $user, SecretariatOffice $office): bool
    {
        if ($this->isAdministrator($user)) {
            return true;
        }

        $group = $this->groupScope($office);
        return $group !== null && $this->membership($user, $group) !== null;
    }

    public function manage(User $user, SecretariatOffice $office): bool
    {
        if ($this->isAdministrator($user)) {
            return true;
        }

        $group = $this->groupScope($office);
        return $group !== null && (int) optional($this->membership($user, $group))->role === 3;
    }

    public function inspect(User $user, SecretariatOffice $office): bool
    {
        if ($this->isAdministrator($user)) {
            return true;
        }

        $group = $this->groupScope($office);
        return $group !== null && in_array((int) optional($this->membership($user, $group))->role, [2, 3], true);
    }

    private function groupScope(SecretariatOffice $office): ?Group
    {
        if ($office->scope_type !== 'group' || $office->scope_id === null) {
            return null;
        }

        return Group::query()->find($office->scope_id);
    }
}
