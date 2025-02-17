<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Alley;

class AlleySeeder extends Seeder
{
    public function run()
    {
        $alleys = [
            // کوچه‌های خیابان ولیعصر
            ['name' => 'Alley 1', 'national_code' => '001', 'street_id' => 2], // Assuming the ID of Valiasr Street is 2
            ['name' => 'Alley 2', 'national_code' => '002', 'street_id' => 2],
            ['name' => 'Alley 3', 'national_code' => '003', 'street_id' => 2],
            ['name' => 'Alley 4', 'national_code' => '004', 'street_id' => 2],
            ['name' => 'Alley 5', 'national_code' => '005', 'street_id' => 2],
            ['name' => 'Alley 6', 'national_code' => '006', 'street_id' => 2],
            ['name' => 'Alley 7', 'national_code' => '007', 'street_id' => 2],
            ['name' => 'Alley 8', 'national_code' => '008', 'street_id' => 2],
            ['name' => 'Alley 9', 'national_code' => '009', 'street_id' => 2],
            ['name' => 'Alley 10', 'national_code' => '010', 'street_id' => 2],
        ];

        foreach ($alleys as $alley) {
            Alley::create($alley);
        }
    }
}