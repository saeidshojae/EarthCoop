<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\IndustrialField;

class IndustrialFieldSeeder extends Seeder
{
    public function run()
    {
        $fields = [
            ['title' => 'Field 1', 'parent_id' => null, 'level' => 1],
            ['title' => 'Field 2', 'parent_id' => null, 'level' => 1],
            ['title' => 'Subfield 1-1', 'parent_id' => 1, 'level' => 2],
            ['title' => 'Subfield 1-2', 'parent_id' => 1, 'level' => 2],
            ['title' => 'Subfield 2-1', 'parent_id' => 2, 'level' => 2],
        ];

        foreach ($fields as $field) {
            IndustrialField::create($field);
        }
    }
}