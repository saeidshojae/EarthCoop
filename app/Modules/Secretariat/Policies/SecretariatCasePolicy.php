<?php

namespace App\Modules\Secretariat\Policies;

use App\Models\User;
use App\Modules\Secretariat\Models\SecretariatCase;
use App\Policies\Concerns\ResolvesGroupMembership;

class SecretariatCasePolicy
{
    use ResolvesGroupMembership;

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
            // leadership is oversight-visible: it distinguishes workflow authority,
            // not the shareholder/member right to inspect related management data.
            'public', 'office_members', 'leadership' => true,
            // Case-specific ACL is not part of the current S5 slice. Until it is,
            // sensitive case metadata remains limited to the canonical global admin.
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
}
