<?php

namespace App\Modules\Secretariat\Policies;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatCase;

class SecretariatCasePolicy
{
    public function __construct(private readonly SecretariatOfficePolicy $offices)
    {
    }

    public function view(User $user, SecretariatCase $case): bool
    {
        $office = $case->relationLoaded('office') ? $case->office : $case->office()->first();
        if ($office === null || ! $this->offices->view($user, $office)) {
            return false;
        }

        return match ((string) $case->confidentiality) {
            'public', 'office_members' => true,
            'leadership' => $this->offices->inspect($user, $office),
            // Case-specific ACL is not part of the current S5 slice. Do not fake
            // one through office membership: sensitive case metadata stays admin-only
            // until an explicit case ACL contract exists.
            'restricted', 'confidential' => $this->isAdministrator($user),
            default => false,
        };
    }

    public function create(User $user, SecretariatCase $case): bool
    {
        $office = $case->relationLoaded('office') ? $case->office : $case->office()->first();
        return $office !== null && $this->offices->inspect($user, $office);
    }

    public function manage(User $user, SecretariatCase $case): bool
    {
        $office = $case->relationLoaded('office') ? $case->office : $case->office()->first();
        return $office !== null && $this->offices->manage($user, $office);
    }

    private function isAdministrator(User $user): bool
    {
        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return true;
        }

        return isset($user->role) && (string) $user->role === 'admin';
    }
}
