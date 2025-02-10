<?php

namespace Database\Seeders;

use App\Models\Specialization;
use Illuminate\Database\Seeder;

class SpecializationSeeder extends Seeder
{
    public function run()
    {
        // ایجاد رشته‌های تخصصی
        $humanities = Specialization::create([
            'title' => 'علوم انسانی',
            'level' => 1,
        ]);

        $law = Specialization::create([
            'title' => 'حقوق',
            'parent_id' => $humanities->id,
            'level' => 2,
        ]);

        $civilLaw = Specialization::create([
            'title' => 'حقوق مدنی',
            'parent_id' => $law->id,
            'level' => 3,
        ]);
    }
}