<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Street;

class StreetSeeder extends Seeder
{
    public function run()
    {
        $streets = [
            // خیابان‌های محله تجریش
            ['name' => 'Shariati Street', 'national_code' => '001', 'neighborhood_id' => 7], // Assuming the ID of Tajrish neighborhood is 7
            ['name' => 'Valiasr Street', 'national_code' => '002', 'neighborhood_id' => 7],
            ['name' => 'Saad Abad Street', 'national_code' => '003', 'neighborhood_id' => 7],
            ['name' => 'Darband Street', 'national_code' => '004', 'neighborhood_id' => 7],
            ['name' => 'Zafaraniyeh Street', 'national_code' => '005', 'neighborhood_id' => 7],
            ['name' => 'Elahieh Street', 'national_code' => '006', 'neighborhood_id' => 7],
            ['name' => 'Niavaran Street', 'national_code' => '007', 'neighborhood_id' => 7],
            ['name' => 'Farmanieh Street', 'national_code' => '008', 'neighborhood_id' => 7],
            ['name' => 'Jamaran Street', 'national_code' => '009', 'neighborhood_id' => 7],
            ['name' => 'Darakeh Street', 'national_code' => '010', 'neighborhood_id' => 7],
        ];

        foreach ($streets as $street) {
            Street::create($street);
        }
    }
}