<?php

namespace Database\Seeders;

use App\Models\IndustrialField;
use Illuminate\Database\Seeder;

class IndustrialFieldSeeder extends Seeder
{
    public function run()
    {
        // ایجاد رسته‌های صنفی
        $industry = IndustrialField::create([
            'title' => 'صنعت',
            'level' => 1,
        ]);

        $foodIndustry = IndustrialField::create([
            'title' => 'صنایع غذایی',
            'parent_id' => $industry->id,
            'level' => 2,
        ]);

        $dairyIndustry = IndustrialField::create([
            'title' => 'صنایع لبنی',
            'parent_id' => $foodIndustry->id,
            'level' => 3,
        ]);
    }
}