<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Province;

class ProvinceSeeder extends Seeder
{
    public function run()
    {
        $provinces = [
            ['name_en' => 'Tehran', 'name_local' => 'تهران', 'country_id' => 1], // Assuming the ID of Iran is 1
            ['name_en' => 'Isfahan', 'name_local' => 'اصفهان', 'country_id' => 1],
            ['name_en' => 'Khorasan Razavi', 'name_local' => 'خراسان رضوی', 'country_id' => 1],
            ['name_en' => 'Fars', 'name_local' => 'فارس', 'country_id' => 1],
            ['name_en' => 'East Azerbaijan', 'name_local' => 'آذربایجان شرقی', 'country_id' => 1],
            ['name_en' => 'West Azerbaijan', 'name_local' => 'آذربایجان غربی', 'country_id' => 1],
            ['name_en' => 'Khuzestan', 'name_local' => 'خوزستان', 'country_id' => 1],
            ['name_en' => 'Mazandaran', 'name_local' => 'مازندران', 'country_id' => 1],
            ['name_en' => 'Gilan', 'name_local' => 'گیلان', 'country_id' => 1],
            ['name_en' => 'Qom', 'name_local' => 'قم', 'country_id' => 1],
            ['name_en' => 'Kermanshah', 'name_local' => 'کرمانشاه', 'country_id' => 1],
            ['name_en' => 'Kerman', 'name_local' => 'کرمان', 'country_id' => 1],
            ['name_en' => 'Sistan and Baluchestan', 'name_local' => 'سیستان و بلوچستان', 'country_id' => 1],
            ['name_en' => 'Golestan', 'name_local' => 'گلستان', 'country_id' => 1],
            ['name_en' => 'Semnan', 'name_local' => 'سمنان', 'country_id' => 1],
            ['name_en' => 'Qazvin', 'name_local' => 'قزوین', 'country_id' => 1],
            ['name_en' => 'Zanjan', 'name_local' => 'زنجان', 'country_id' => 1],
            ['name_en' => 'Lorestan', 'name_local' => 'لرستان', 'country_id' => 1],
            ['name_en' => 'Hormozgan', 'name_local' => 'هرمزگان', 'country_id' => 1],
            ['name_en' => 'Markazi', 'name_local' => 'مرکزی', 'country_id' => 1],
            ['name_en' => 'Yazd', 'name_local' => 'یزد', 'country_id' => 1],
            ['name_en' => 'Ardabil', 'name_local' => 'اردبیل', 'country_id' => 1],
            ['name_en' => 'Bushehr', 'name_local' => 'بوشهر', 'country_id' => 1],
            ['name_en' => 'Chaharmahal and Bakhtiari', 'name_local' => 'چهارمحال و بختیاری', 'country_id' => 1],
            ['name_en' => 'Ilam', 'name_local' => 'ایلام', 'country_id' => 1],
            ['name_en' => 'Kohgiluyeh and Boyer-Ahmad', 'name_local' => 'کهگیلویه و بویراحمد', 'country_id' => 1],
            ['name_en' => 'North Khorasan', 'name_local' => 'خراسان شمالی', 'country_id' => 1],
            ['name_en' => 'South Khorasan', 'name_local' => 'خراسان جنوبی', 'country_id' => 1],
            ['name_en' => 'Alborz', 'name_local' => 'البرز', 'country_id' => 1],
            ['name_en' => 'Kurdistan', 'name_local' => 'کردستان', 'country_id' => 1],
            ['name_en' => 'Hamedan', 'name_local' => 'همدان', 'country_id' => 1],
        ];

        foreach ($provinces as $province) {
            Province::create($province);
        }
    }
}