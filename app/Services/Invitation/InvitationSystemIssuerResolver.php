<?php

namespace App\Services\Invitation;

use App\Models\User;
use RuntimeException;

class InvitationSystemIssuerResolver
{
    public function id(): ?int
    {
        $configured = config('invitation-management.system_issuer_user_id');

        if ($configured === null || $configured === '') {
            return null;
        }

        if (! is_numeric($configured) || (int) $configured <= 0) {
            throw new RuntimeException('Configured invitation system issuer id is invalid.');
        }

        $id = (int) $configured;
        if (! User::query()->whereKey($id)->exists()) {
            throw new RuntimeException('Configured invitation system issuer user does not exist.');
        }

        return $id;
    }
}
