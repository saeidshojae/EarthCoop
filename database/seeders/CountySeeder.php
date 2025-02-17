<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\County;

class CountySeeder extends Seeder
{
    public function run()
    {
        $counties = [
            ['name' => 'Tehran', 'national_code' => '001', 'province_id' => 1], // Assuming the ID of Tehran province is 1
            ['name' => 'Shemiranat', 'national_code' => '002', 'province_id' => 1],
            ['name' => 'Rey', 'national_code' => '003', 'province_id' => 1],
            ['name' => 'Eslamshahr', 'national_code' => '004', 'province_id' => 1],
            ['name' => 'Pakdasht', 'national_code' => '005', 'province_id' => 1],
            ['name' => 'Varamin', 'national_code' => '006', 'province_id' => 1],
            ['name' => 'Shahriar', 'national_code' => '007', 'province_id' => 1],
            ['name' => 'Malard', 'national_code' => '008', 'province_id' => 1],
            ['name' => 'Qods', 'national_code' => '009', 'province_id' => 1],
            ['name' => 'Robat Karim', 'national_code' => '010', 'province_id' => 1],
            ['name' => 'Baharestan', 'national_code' => '011', 'province_id' => 1],
            ['name' => 'Pardis', 'national_code' => '012', 'province_id' => 1],
            ['name' => 'Damavand', 'national_code' => '013', 'province_id' => 1],
            ['name' => 'Firuzkuh', 'national_code' => '014', 'province_id' => 1],
        ];

        foreach ($counties as $county) {
            County::create($county);
        }
    }
}