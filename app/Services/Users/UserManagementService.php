<?php

namespace App\Services\Users;

use App\Models\User;

class UserManagementService
{
    /** @return array<string,mixed> */
    public function suspend(User $user): array
    {
        if ($user->isSystemIdentity()) {
            return [
                'success' => false,
                'status' => 'blocked',
                'reason' => 'system_identity_protected',
                'user_id' => (int) $user->id,
            ];
        }

        if ((string) $user->status === 'suspended') {
            return [
                'success' => true,
                'status' => 'already_suspended',
                'user_id' => (int) $user->id,
            ];
        }

        $user->update(['status' => 'suspended']);

        return [
            'success' => true,
            'status' => 'suspended',
            'user_id' => (int) $user->id,
        ];
    }

    /** @return array<string,mixed> */
    public function setStatus(User $user, string $status): array
    {
        if (! in_array($status, ['active', 'inactive', 'suspended'], true)) {
            return [
                'success' => false,
                'status' => 'invalid_status',
                'user_id' => (int) $user->id,
            ];
        }

        if ($status === 'suspended') {
            return $this->suspend($user);
        }

        if ($user->isSystemIdentity()) {
            return [
                'success' => false,
                'status' => 'blocked',
                'reason' => 'system_identity_protected',
                'user_id' => (int) $user->id,
            ];
        }

        $user->update(['status' => $status]);

        return [
            'success' => true,
            'status' => $status,
            'user_id' => (int) $user->id,
        ];
    }
}
