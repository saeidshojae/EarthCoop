<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AgeGroup;

class AgeGroupsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ترتیب درج مطابق با دیتابیس قدیم (earthcoopdb)
        AgeGroup::upsert([
            [
                'min_age' => 15,
                'max_age' => 20,
                'title' => 'نوجوانان',        // ID = 1
            ],
            [
                'min_age' => 21,
                'max_age' => 25,
                'title' => 'جوانان',           // ID = 2
            ],
            [
                'min_age' => 26,
                'max_age' => 35,
                'title' => 'بزرگسالان جوان',   // ID = 3
            ],
            [
                'min_age' => 36,
                'max_age' => 50,
                'title' => 'میانسالان',        // ID = 4
            ],
            [
                'min_age' => 51,
                'max_age' => 100,
                'title' => 'سالمندان',         // ID = 5
            ],
            [
                'min_age' => 0,
                'max_age' => 15,
                'title' => 'کودکان',             // ID = 6
            ],
        ], ['min_age', 'max_age'], ['title']);
    }
}