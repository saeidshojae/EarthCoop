<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // اجرای سیدرها به ترتیب
        $this->call([
            JobFieldSeeder::class,
            SpecializationSeeder::class,
            UserSeeder::class,
            ContinentSeeder::class,
            CountrySeeder::class,
            ProvinceSeeder::class,
            CountySeeder::class,
            DistrictSeeder::class,
            SettlementSeeder::class,
            LocalitySeeder::class,
            NeighborhoodSeeder::class,
            StreetSeeder::class,
            AlleySeeder::class,
        ]);
    }
}