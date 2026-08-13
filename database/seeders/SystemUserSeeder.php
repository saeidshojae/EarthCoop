<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SystemUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
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
    }
}
