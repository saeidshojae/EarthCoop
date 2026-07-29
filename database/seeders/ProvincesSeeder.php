<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProvincesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // درج تمام استان‌ها
        DB::table('provinces')->insert([
            ['id' => 1, 'name' => 'آذربایجان شرقی', 'amar_code' => '3', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 2, 'name' => 'آذربایجان غربی', 'amar_code' => '4', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 3, 'name' => 'اردبیل', 'amar_code' => '24', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 4, 'name' => 'اصفهان', 'amar_code' => '10', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 5, 'name' => 'البرز', 'amar_code' => '30', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 6, 'name' => 'ایلام', 'amar_code' => '16', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 7, 'name' => 'بوشهر', 'amar_code' => '18', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 8, 'name' => 'تهران', 'amar_code' => '23', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 9, 'name' => 'چهارمحال وبختیاری', 'amar_code' => '14', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 10, 'name' => 'خراسان جنوبی', 'amar_code' => '29', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 11, 'name' => 'خراسان رضوی', 'amar_code' => '9', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 12, 'name' => 'خراسان شمالی', 'amar_code' => '28', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 13, 'name' => 'خوزستان', 'amar_code' => '6', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 14, 'name' => 'زنجان', 'amar_code' => '19', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 15, 'name' => 'سمنان', 'amar_code' => '20', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 16, 'name' => 'سیستان وبلوچستان', 'amar_code' => '11', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 17, 'name' => 'فارس', 'amar_code' => '7', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 18, 'name' => 'قزوین', 'amar_code' => '26', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 19, 'name' => 'قم', 'amar_code' => '25', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 20, 'name' => 'کردستان', 'amar_code' => '12', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 21, 'name' => 'کرمان', 'amar_code' => '8', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 22, 'name' => 'کرمانشاه', 'amar_code' => '5', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 23, 'name' => 'کهگیلویه وبویراحمد', 'amar_code' => '17', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 24, 'name' => 'گلستان', 'amar_code' => '27', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 25, 'name' => 'گیلان', 'amar_code' => '1', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 26, 'name' => 'لرستان', 'amar_code' => '15', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 27, 'name' => 'مازندران', 'amar_code' => '2', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 28, 'name' => 'مرکزی', 'amar_code' => '0', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 29, 'name' => 'هرمزگان', 'amar_code' => '22', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 30, 'name' => 'همدان', 'amar_code' => '13', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 31, 'name' => 'یزد', 'amar_code' => '21', 'country_id' => 74, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 32, 'name' => 'استان المان', 'amar_code' => '61', 'country_id' => 61, 'status' => 0, 'created_at' => null, 'updated_at' => null],
        ]);
    }
}