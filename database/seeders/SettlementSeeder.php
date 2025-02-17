<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Settlement;

class SettlementSeeder extends Seeder
{
    public function run()
    {
        $settlements = [
            // بخش مرکزی
            ['name' => 'Tehran', 'type' => 'city', 'national_code' => '001', 'district_id' => 1], // Assuming the ID of Central District is 1
            ['name' => 'Siyahroud', 'type' => 'rural', 'national_code' => '002', 'district_id' => 1],

            // بخش آفتاب
            ['name' => 'Aftab', 'type' => 'city', 'national_code' => '003', 'district_id' => 2], // Assuming the ID of Aftab District is 2
            ['name' => 'Aftab', 'type' => 'rural', 'national_code' => '004', 'district_id' => 2],
            ['name' => 'Khalazir', 'type' => 'rural', 'national_code' => '005', 'district_id' => 2],

            // بخش کن
            ['name' => 'Kan', 'type' => 'city', 'national_code' => '006', 'district_id' => 3], // Assuming the ID of Kan District is 3
            ['name' => 'Sulqan', 'type' => 'rural', 'national_code' => '007', 'district_id' => 3],
        ];

        foreach ($settlements as $settlement) {
            Settlement::create($settlement);
        }
    }
}