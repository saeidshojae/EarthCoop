<?php

namespace App\Modules\Secretariat\Policies;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatAclService;
use App\Policies\Concerns\ResolvesGroupMembership;

class SecretariatRecordPolicy
{
    use ResolvesGroupMembership;

    public function __construct(
        private readonly SecretariatAclService $acl,
        private readonly SecretariatOfficePolicy $offices,
    ) {
    }

    public function view(User $user, SecretariatRecord $record): bool
    {
        if ($this->isAdministrator($user)) {
            return true;
        }

        // Sensitive records remain explicit-capability resources. Shareholder
        // transparency never implies exposure of personal/security/legal material
        // that has deliberately been classified restricted or confidential.
        if (in_array($record->confidentiality, ['restricted', 'confidential'], true)) {
            return $this->acl->allows($user, $record, 'view');
        }

        $office = $record->relationLoaded('office') ? $record->office : $record->office()->first();
        if ($office === null || ! $this->offices->view($user, $office)) {
            return false;
        }

        // public, office_members and leadership are all oversight-visible to a
        // principal who can legitimately enter the office. "leadership" remains
        // a workflow/management label, not a secrecy boundary.
        return in_array($record->confidentiality, ['public', 'office_members', 'leadership'], true);
    }

    public function create(User $user, SecretariatRecord $record): bool
    {
        return $this->canPrepareOfficeRecord($user, $record);
    }

    public function update(User $user, SecretariatRecord $record): bool
    {
        return $record->status === 'draft' && $this->canPrepareOfficeRecord($user, $record);
    }

    public function submitForApproval(User $user, SecretariatRecord $record): bool
    {
        return $record->status === 'draft' && $this->canPrepareOfficeRecord($user, $record);
    }

    public function register(User $user, SecretariatRecord $record): bool
    {
        return $record->status === 'pending_approval' && $this->canManageOffice($user, $record);
    }

    public function transition(User $user, SecretariatRecord $record): bool
    {
        // Inspectors may prepare/review drafts, but formal lifecycle effects
        // (activate/close/archive/void/supersede) belong to the office manager.
        return $this->canManageOffice($user, $record);
    }

    public function manageAcl(User $user, SecretariatRecord $record): bool
    {
        return $this->canManageOffice($user, $record);
    }

    public function delete(User $user, SecretariatRecord $record): bool
    {
        return in_array($record->status, ['draft', 'cancelled'], true)
            && $this->canPrepareOfficeRecord($user, $record);
    }

    private function canPrepareOfficeRecord(User $user, SecretariatRecord $record): bool
    {
        $office = $record->relationLoaded('office') ? $record->office : $record->office()->first();
        return $office !== null && $this->offices->inspect($user, $office);
    }

    private function canManageOffice(User $user, SecretariatRecord $record): bool
    {
        $office = $record->relationLoaded('office') ? $record->office : $record->office()->first();
        return $office !== null && $this->offices->manage($user, $office);
    }
}
