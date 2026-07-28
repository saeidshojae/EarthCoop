<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([

            /*
            |--------------------------------------------------------------------------
            | Lookup Data
            |--------------------------------------------------------------------------
            */

            AgeGroupsSeeder::class,
            ContinentsSeeder::class,
            CountriesSeeder::class,
            ProvincesSeeder::class,
            CountiesSeeder::class,
            DistrictsSeeder::class,
            CitiesSeeder::class,
            RegionsSeeder::class,
            NeighborhoodsSeeder::class,

            OccupationalFieldsSeeder::class,
            ExperienceFieldsSeeder::class,
            
            SettingSeeder::class,

            PagesTableSeeder::class,

            KnowledgeBaseSeeder::class,

            EarthCoopBlogSeeder::class,
            /*
            |--------------------------------------------------------------------------
            | Authorization
            |--------------------------------------------------------------------------
            */

            RolePermissionSeeder::class,

            /*
            |--------------------------------------------------------------------------
            | Core System
            |--------------------------------------------------------------------------
            */

            StockSeeder::class,
            NajmBaharSeeder::class,
            NajmBaharProjectCategorySeeder::class,

        ]);
    }
}