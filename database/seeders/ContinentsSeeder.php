<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContinentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('continents')->insert([
            [
                'id' => 1,
                'name' => 'آفریقا',
                'status' => 1,
                'created_at' => null,
                'updated_at' => null,
            ],
            [
                'id' => 2,
                'name' => 'آمریکای شمالی',
                'status' => 1,
                'created_at' => null,
                'updated_at' => null,
            ],
            [
                'id' => 3,
                'name' => 'آمریکای جنوبی',
                'status' => 1,
                'created_at' => null,
                'updated_at' => null,
            ],
            [
                'id' => 4,
                'name' => 'آسیا',
                'status' => 1,
                'created_at' => null,
                'updated_at' => null,
            ],
            [
                'id' => 5,
                'name' => 'اروپا',
                'status' => 1,
                'created_at' => null,
                'updated_at' => null,
            ],
            [
                'id' => 6,
                'name' => 'استرالیا و اقیانوسیه',
                'status' => 1,
                'created_at' => null,
                'updated_at' => null,
            ],
            [
                'id' => 7,
                'name' => 'قطب جنوب',
                'status' => 1,
                'created_at' => null,
                'updated_at' => null,
            ],
        ]);
    }
}