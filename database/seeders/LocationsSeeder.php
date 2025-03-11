<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocationsSeeder extends Seeder
{
    public function run()
    {
        // داده‌های قاره‌ها
        $continents = [
            ['id' => 1, 'name' => 'آسیا', 'parent_id' => null, 'level' => 'continent'],
            ['id' => 2, 'name' => 'اروپا', 'parent_id' => null, 'level' => 'continent'],
            ['id' => 3, 'name' => 'آفریقا', 'parent_id' => null, 'level' => 'continent'],
            // سایر قاره‌ها می‌توانند اضافه شوند
        ];

        // داده‌های کشورهای خاورمیانه و اروپا
        $countries = [
            ['id' => 4, 'name' => 'ایران', 'parent_id' => 1, 'level' => 'country'],
            ['id' => 5, 'name' => 'عراق', 'parent_id' => 1, 'level' => 'country'],
            ['id' => 6, 'name' => 'ترکیه', 'parent_id' => 1, 'level' => 'country'],
            ['id' => 7, 'name' => 'فرانسه', 'parent_id' => 2, 'level' => 'country'],
            ['id' => 8, 'name' => 'آلمان', 'parent_id' => 2, 'level' => 'country'],
            ['id' => 9, 'name' => 'انگلیس', 'parent_id' => 2, 'level' => 'country'],
            // سایر کشورهای خاورمیانه و اروپا می‌توانند اضافه شوند
        ];

        // داده‌های استان‌های ایران
        $provinces = [
            ['id' => 10, 'name' => 'تهران', 'parent_id' => 4, 'level' => 'province'],
            ['id' => 11, 'name' => 'مازندران', 'parent_id' => 4, 'level' => 'province'],
            ['id' => 12, 'name' => 'اصفهان', 'parent_id' => 4, 'level' => 'province'],
            // سایر استان‌ها می‌توانند اضافه شوند (31 استان)
        ];

        // داده‌های شهرستان‌های استان تهران و مازندران
        $counties = [
            ['id' => 13, 'name' => 'شهرستان تهران', 'parent_id' => 10, 'level' => 'county'],
            ['id' => 14, 'name' => 'شهرستان شمیرانات', 'parent_id' => 10, 'level' => 'county'],
            ['id' => 15, 'name' => 'شهرستان ساری', 'parent_id' => 11, 'level' => 'county'],
            ['id' => 16, 'name' => 'شهرستان قائم‌شهر', 'parent_id' => 11, 'level' => 'county'],
            // سایر شهرستان‌ها می‌توانند اضافه شوند
        ];

        // داده‌های بخش‌های شهرستان تهران و ساری
        $districts = [
            ['id' => 17, 'name' => 'بخش مرکزی تهران', 'parent_id' => 13, 'level' => 'district'],
            ['id' => 18, 'name' => 'بخش تجریش', 'parent_id' => 14, 'level' => 'district'],
            ['id' => 19, 'name' => 'بخش مرکزی ساری', 'parent_id' => 15, 'level' => 'district'],
            ['id' => 20, 'name' => 'بخش میاندرود', 'parent_id' => 16, 'level' => 'district'],
        ];

        // داده‌های شهرها
        $cities = [
            ['id' => 21, 'name' => 'تهران', 'parent_id' => 13, 'level' => 'city'],
            ['id' => 22, 'name' => 'شمیران', 'parent_id' => 14, 'level' => 'city'],
            ['id' => 23, 'name' => 'ساری', 'parent_id' => 15, 'level' => 'city'],
            ['id' => 24, 'name' => 'قائم‌شهر', 'parent_id' => 16, 'level' => 'city'],
        ];

        // داده‌های مناطق شهری
        $areas = [
            ['id' => 25, 'name' => 'منطقه 1 تهران', 'parent_id' => 21, 'level' => 'area'],
            ['id' => 26, 'name' => 'منطقه 2 تهران', 'parent_id' => 21, 'level' => 'area'],
            ['id' => 27, 'name' => 'منطقه 1 ساری', 'parent_id' => 23, 'level' => 'area'],
            // سایر مناطق می‌توانند اضافه شوند
        ];

        // داده‌های محلات
        $neighborhoods = [
            ['id' => 28, 'name' => 'محله تجریش', 'parent_id' => 25, 'level' => 'neighborhood'],
            ['id' => 29, 'name' => 'محله نیاوران', 'parent_id' => 25, 'level' => 'neighborhood'],
            ['id' => 30, 'name' => 'محله ساری', 'parent_id' => 27, 'level' => 'neighborhood'],
        ];

        // داده‌های خیابان‌ها و کوچه‌ها
        $streets = [
            ['id' => 31, 'name' => 'خیابان ولیعصر', 'parent_id' => 28, 'level' => 'street'],
            ['id' => 32, 'name' => 'خیابان شریعتی', 'parent_id' => 28, 'level' => 'street'],
            ['id' => 33, 'name' => 'کوچه ساری', 'parent_id' => 30, 'level' => 'street'],
        ];

        // وارد کردن داده‌ها به جداول
        DB::table('locations')->insert(array_merge($continents, $countries, $provinces, $counties, $districts, $cities, $areas, $neighborhoods, $streets));
    }
}
