<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SystemUserSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['support', 'management'] as $identity) {
            $definition = (array) config("system-identities.{$identity}", []);
            $email = trim((string) ($definition['email'] ?? ''));
            if ($email === '') {
                continue;
            }

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => (string) ($definition['first_name'] ?? ''),
                    'last_name' => (string) ($definition['last_name'] ?? 'EarthCoop'),
                    'password' => Hash::make(bin2hex(random_bytes(32))),
                    'status' => 'active',
                    'is_system' => true,
                    'email_verified_at' => now(),
                    'terms_accepted_at' => now(),
                ]
            );

            // Technical/team identities may author official system content, but
            // they are never interactive administrators or cooperative members.
            $user->forceFill(['is_admin' => false])->save();
            $user->groups()->detach();
            $user->roles()->detach();
        }
    }
}
