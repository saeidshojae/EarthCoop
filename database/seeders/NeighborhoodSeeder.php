<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Neighborhood;

class NeighborhoodSeeder extends Seeder
{
    public function run()
    {
        $neighborhoods = [
            // محله‌های منطقه 1 تهران
            ['name' => 'Zaferanieh', 'national_code' => '001', 'locality_id' => 1], // Assuming the ID of District 1 is 1
            ['name' => 'Elahieh', 'national_code' => '002', 'locality_id' => 1],
            ['name' => 'Niavaran', 'national_code' => '003', 'locality_id' => 1],
            ['name' => 'Farmanieh', 'national_code' => '004', 'locality_id' => 1],
            ['name' => 'Darband', 'national_code' => '005', 'locality_id' => 1],
            ['name' => 'Jamaran', 'national_code' => '006', 'locality_id' => 1],
            ['name' => 'Tajrish', 'national_code' => '007', 'locality_id' => 1],
            ['name' => 'Qeytarieh', 'national_code' => '008', 'locality_id' => 1],
            ['name' => 'Darakeh', 'national_code' => '009', 'locality_id' => 1],
            ['name' => 'Velenjak', 'national_code' => '010', 'locality_id' => 1],
            ['name' => 'Chizar', 'national_code' => '011', 'locality_id' => 1],
            ['name' => 'Evin', 'national_code' => '012', 'locality_id' => 1],
            ['name' => 'Kamraniyeh', 'national_code' => '013', 'locality_id' => 1],
            ['name' => 'Aghdasieh', 'national_code' => '014', 'locality_id' => 1],
            ['name' => 'Manzariyeh', 'national_code' => '015', 'locality_id' => 1],
            ['name' => 'Shemiran', 'national_code' => '016', 'locality_id' => 1],
            ['name' => 'Golestan', 'national_code' => '017', 'locality_id' => 1],
            ['name' => 'Darrous', 'national_code' => '018', 'locality_id' => 1],
            ['name' => 'Gheytarieh', 'national_code' => '019', 'locality_id' => 1],
            ['name' => 'Zargandeh', 'national_code' => '020', 'locality_id' => 1],
        ];

        foreach ($neighborhoods as $neighborhood) {
            Neighborhood::create($neighborhood);
        }
    }
}