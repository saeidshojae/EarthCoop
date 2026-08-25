<?php

namespace App\Services;

use App\Models\User;
use RuntimeException;

class SystemIdentityService
{
    public function support(): User
    {
        return $this->resolve('support');
    }

    public function management(): User
    {
        return $this->resolve('management');
    }

    public function mailSender(string $identity): array
    {
        $user = $this->resolve($identity);

        return [
            'address' => (string) $user->email,
            'name' => (string) config("system-identities.{$identity}.mail_from_name", trim($user->first_name . ' ' . $user->last_name)),
        ];
    }

    protected function resolve(string $identity): User
    {
        $email = trim((string) config("system-identities.{$identity}.email"));
        if ($email === '') {
            throw new RuntimeException("system_identity_not_configured:{$identity}");
        }

        $user = User::query()->where('email', $email)->first();
        if (! $user || ! $user->isSystemIdentity()) {
            throw new RuntimeException("system_identity_missing_or_not_system:{$identity}");
        }

        return $user;
    }
}
