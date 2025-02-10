<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // اجرای سیدرها به ترتیب
        $this->call([
            IndustrialFieldSeeder::class,
            SpecializationSeeder::class,
            UserSeeder::class,
        ]);
    }
}