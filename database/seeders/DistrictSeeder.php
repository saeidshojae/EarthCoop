<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\District;

class DistrictSeeder extends Seeder
{
    public function run()
    {
        $districts = [
            ['name' => 'Central District', 'national_code' => '001', 'county_id' => 1], // Assuming the ID of Tehran county is 1
            ['name' => 'Aftab District', 'national_code' => '002', 'county_id' => 1],
            ['name' => 'Kan District', 'national_code' => '003', 'county_id' => 1],
        ];

        foreach ($districts as $district) {
            District::create($district);
        }
    }
}