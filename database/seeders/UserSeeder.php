<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\JobField;
use App\Models\Specialization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        // ایجاد کاربر
        $user = User::create([
            'first_name' => 'سعید',
            'last_name' => 'شجاعی',
            'nationality' => 'ایرانی',
            'national_id' => '1234567890',
            'phone' => '+989121234567',
            'email' => 'saeid@example.com',
            'birth_date' => '1990-01-01',
            'gender' => 'male',
            'password' => Hash::make('password'),
        ]);

        // اتصال کاربر به رسته صنفی "صنایع لبنی" (آی‌دی ۳)
        $jobField = JobField::where('title', 'صنایع لبنی')->first();
        if ($jobField) {
            $user->jobFields()->sync([$jobField->id]);
        }

        // اتصال کاربر به تخصص "حقوق مدنی" (آی‌دی ۳)
        $specialization = Specialization::where('title', 'حقوق مدنی')->first();
        if ($specialization) {
            $user->specializations()->sync([$specialization->id]);
        }
    }
}