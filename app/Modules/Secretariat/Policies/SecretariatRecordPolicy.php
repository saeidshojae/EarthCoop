<?php

namespace App\Modules\Secretariat\Policies;

use App\Models\Group;
use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Policies\Concerns\ResolvesGroupMembership;

class SecretariatRecordPolicy
{
    use ResolvesGroupMembership;

    public function view(User $user, SecretariatRecord $record): bool
    {
        if ($this->isAdministrator($user)) {
            return true;
        }

        $group = $this->groupScope($record);
        if ($group === null) {
            return false;
        }

        $membership = $this->membership($user, $group);
        if ($membership === null) {
            return false;
        }

        return match ($record->confidentiality) {
            'public', 'office_members' => true,
            'leadership' => in_array((int) $membership->role, [2, 3], true),
            // ACL lands in S2. Until then, default-deny sensitive records rather
            // than accidentally leaking them through an incomplete policy.
            'restricted', 'confidential' => false,
            default => false,
        };
    }

    public function create(User $user, SecretariatRecord $record): bool
    {
        return $this->canLeadOffice($user, $record);
    }

    public function update(User $user, SecretariatRecord $record): bool
    {
        return $record->status === 'draft' && $this->canLeadOffice($user, $record);
    }

    public function submitForApproval(User $user, SecretariatRecord $record): bool
    {
        return $record->status === 'draft' && $this->canLeadOffice($user, $record);
    }

    public function register(User $user, SecretariatRecord $record): bool
    {
        if ($record->status !== 'pending_approval') {
            return false;
        }

        if ($this->isAdministrator($user)) {
            return true;
        }

        $group = $this->groupScope($record);
        return $group !== null && (int) optional($this->membership($user, $group))->role === 3;
    }

    public function transition(User $user, SecretariatRecord $record): bool
    {
        return $this->canLeadOffice($user, $record);
    }

    public function delete(User $user, SecretariatRecord $record): bool
    {
        return in_array($record->status, ['draft', 'cancelled'], true)
            && $this->canLeadOffice($user, $record);
    }

    private function canLeadOffice(User $user, SecretariatRecord $record): bool
    {
        if ($this->isAdministrator($user)) {
            return true;
        }

        $group = $this->groupScope($record);
        return $group !== null && in_array((int) optional($this->membership($user, $group))->role, [2, 3], true);
    }

    private function groupScope(SecretariatRecord $record): ?Group
    {
        $office = $record->relationLoaded('office') ? $record->office : $record->office()->first();
        if ($office === null || $office->scope_type !== 'group' || $office->scope_id === null) {
            return null;
        }

        return Group::query()->find($office->scope_id);
    }
}
