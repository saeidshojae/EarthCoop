<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use DB;

class ExperienceFieldsSeeder extends Seeder
{
    public function run()
    {
        // سطح اول
        $level1 = [
            'مهندسی',
            'علوم کامپیوتر',
            'مدیریت'
        ];

        foreach ($level1 as $name) {
            $parentId = DB::table('experience_fields')->insertGetId([
                'name' => $name,
                'parent_id' => null,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // سطح دوم
            $level2 = [
                'مهندسی برق',
                'مهندسی مکانیک',
                'مهندسی نرم‌افزار'
            ];

            foreach ($level2 as $subName) {
                $subParentId = DB::table('experience_fields')->insertGetId([
                    'name' => $subName,
                    'parent_id' => $parentId,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // سطح سوم
                $level3 = [
                    'طراحی مدار',
                    'تحلیل سیستم',
                    'برنامه‌نویسی وب',
                    'برنامه‌نویسی موبایل'
                ];

                foreach ($level3 as $subSubName) {
                    DB::table('experience_fields')->insert([
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
