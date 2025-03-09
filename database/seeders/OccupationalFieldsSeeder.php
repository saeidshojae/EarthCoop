<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use DB;

class OccupationalFieldsSeeder extends Seeder
{
    public function run()
    {
        // سطح اول
        $level1 = [
            'فرهنگی',
            'علمی',
            'هنری'
        ];

        foreach ($level1 as $name) {
            $parentId = DB::table('occupational_fields')->insertGetId([
                'name' => $name,
                'parent_id' => null,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // سطح دوم
            $level2 = [
                'معلم',
                'پژوهشگر',
                'هنرمند'
            ];

            foreach ($level2 as $subName) {
                $subParentId = DB::table('occupational_fields')->insertGetId([
                    'name' => $subName,
                    'parent_id' => $parentId,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // سطح سوم
                $level3 = [
                    'معلم ابتدایی',
                    'معلم متوسطه',
                    'پژوهشگر علوم انسانی',
                    'پژوهشگر علوم طبیعی',
                    'نقاش',
                    'مجسمه‌ساز'
                ];

                foreach ($level3 as $subSubName) {
                    DB::table('occupational_fields')->insert([
                        'name' => $subSubName,
                        'parent_id' => $subParentId,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }
    }
}
