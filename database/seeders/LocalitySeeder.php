<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Locality;

class LocalitySeeder extends Seeder
{
    public function run()
    {
        $localities = [
            // شهر تهران
            ['name' => 'District 1', 'type' => 'urban', 'national_code' => '001', 'settlement_id' => 1], // Assuming the ID of Tehran city is 1
            ['name' => 'District 2', 'type' => 'urban', 'national_code' => '002', 'settlement_id' => 1],
            ['name' => 'District 3', 'type' => 'urban', 'national_code' => '003', 'settlement_id' => 1],
            ['name' => 'District 4', 'type' => 'urban', 'national_code' => '004', 'settlement_id' => 1],
            ['name' => 'District 5', 'type' => 'urban', 'national_code' => '005', 'settlement_id' => 1],
            ['name' => 'District 6', 'type' => 'urban', 'national_code' => '006', 'settlement_id' => 1],
            ['name' => 'District 7', 'type' => 'urban', 'national_code' => '007', 'settlement_id' => 1],
            ['name' => 'District 8', 'type' => 'urban', 'national_code' => '008', 'settlement_id' => 1],
            ['name' => 'District 9', 'type' => 'urban', 'national_code' => '009', 'settlement_id' => 1],
            ['name' => 'District 10', 'type' => 'urban', 'national_code' => '010', 'settlement_id' => 1],
            ['name' => 'District 11', 'type' => 'urban', 'national_code' => '011', 'settlement_id' => 1],
            ['name' => 'District 12', 'type' => 'urban', 'national_code' => '012', 'settlement_id' => 1],
            ['name' => 'District 13', 'type' => 'urban', 'national_code' => '013', 'settlement_id' => 1],
            ['name' => 'District 14', 'type' => 'urban', 'national_code' => '014', 'settlement_id' => 1],
            ['name' => 'District 15', 'type' => 'urban', 'national_code' => '015', 'settlement_id' => 1],
            ['name' => 'District 16', 'type' => 'urban', 'national_code' => '016', 'settlement_id' => 1],
            ['name' => 'District 17', 'type' => 'urban', 'national_code' => '017', 'settlement_id' => 1],
            ['name' => 'District 18', 'type' => 'urban', 'national_code' => '018', 'settlement_id' => 1],
            ['name' => 'District 19', 'type' => 'urban', 'national_code' => '019', 'settlement_id' => 1],
            ['name' => 'District 20', 'type' => 'urban', 'national_code' => '020', 'settlement_id' => 1],
            ['name' => 'District 21', 'type' => 'urban', 'national_code' => '021', 'settlement_id' => 1],
            ['name' => 'District 22', 'type' => 'urban', 'national_code' => '022', 'settlement_id' => 1],

            // دهستان سیاهرود
            ['name' => 'Siyahroud Village 1', 'type' => 'rural', 'national_code' => '023', 'settlement_id' => 2], // Assuming the ID of Siyahroud rural district is 2
            ['name' => 'Siyahroud Village 2', 'type' => 'rural', 'national_code' => '024', 'settlement_id' => 2],
        ];

        foreach ($localities as $locality) {
            Locality::create($locality);
        }
    }
}