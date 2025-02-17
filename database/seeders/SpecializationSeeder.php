<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Specialization;

class SpecializationSeeder extends Seeder
{
    public function run()
    {
        $specializations = [
            ['title' => 'Specialization 1', 'industrial_field_id' => 1, 'level' => 3],
            ['title' => 'Specialization 2', 'industrial_field_id' => 2, 'level' => 3],
            ['title' => 'Specialization 3', 'industrial_field_id' => 3, 'level' => 3],
        ];

        foreach ($specializations as $specialization) {
            Specialization::create($specialization);
        }
    }
}