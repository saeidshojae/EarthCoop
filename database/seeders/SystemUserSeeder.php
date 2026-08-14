<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SystemUserSeeder extends Seeder
{
    public function run(): void
    {
        $support = User::updateOrCreate(
            ['email' => 'support@earthcoop.ir'],
            [
                'first_name' => 'تیم پشتیبانی',
                'last_name' => 'EarthCoop',
                'password' => Hash::make(bin2hex(random_bytes(32))),
                'status' => 'active',
                'is_system' => true,
                'email_verified_at' => now(),
                'terms_accepted_at' => now(),
            ]
        );

        // A technical identity may author system content, but it is never an
        // interactive administrator or a cooperative/group member.
        $support->forceFill(['is_admin' => false])->save();
        $support->groups()->detach();
        $support->roles()->detach();
    }
}
