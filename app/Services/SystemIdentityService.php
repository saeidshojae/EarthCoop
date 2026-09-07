<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
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

    /** @return array{address:string,name:string,reply_to:string} */
    public function mailSender(string $identity): array
    {
        $user = $this->resolve($identity);
        $address = (string) $user->email;

        return [
            'address' => $address,
            'name' => (string) config("system-identities.{$identity}.mail_from_name", trim($user->first_name . ' ' . $user->last_name)),
            'reply_to' => (string) config("system-identities.{$identity}.reply_to", $address),
        ];
    }

    protected function resolve(string $identity): User
    {
        $definition = (array) config("system-identities.{$identity}", []);
        $email = trim((string) ($definition['email'] ?? ''));
        if ($email === '') {
            throw new RuntimeException("system_identity_not_configured:{$identity}");
        }

        $existing = User::query()->where('email', $email)->first();
        if ($existing && ! $existing->isSystemIdentity()) {
            throw new RuntimeException("system_identity_email_belongs_to_member:{$identity}");
        }

        if (! $existing) {
            $existing = User::query()->create([
                'email' => $email,
                'first_name' => (string) ($definition['first_name'] ?? ''),
                'last_name' => (string) ($definition['last_name'] ?? 'EarthCoop'),
                'password' => Hash::make(bin2hex(random_bytes(32))),
                'status' => 'active',
                'is_system' => true,
                'email_verified_at' => now(),
                'terms_accepted_at' => now(),
            ]);
        }

        $existing->forceFill([
            'first_name' => (string) ($definition['first_name'] ?? $existing->first_name),
            'last_name' => (string) ($definition['last_name'] ?? $existing->last_name),
            'status' => 'active',
            'is_system' => true,
            'is_admin' => false,
        ])->save();

        return $existing->refresh();
    }
}
