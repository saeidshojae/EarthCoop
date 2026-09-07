<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
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
            ElectionGroupSettingSeeder::class,
            PagesTableSeeder::class,
            FaqContactPagesSeeder::class,

            RolePermissionSeeder::class,
            SystemUserSeeder::class,
            ElectionResponsibilityContractSeeder::class,

            KnowledgeBaseSeeder::class,
            EarthCoopBlogSeeder::class,
            NajmBaharSeeder::class,
            NajmBaharProjectCategorySeeder::class,
        ]);
    }
}
