<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Province;
use App\Models\County;
use App\Models\District;
use App\Models\City;
use App\Models\Region;
use App\Models\Neighborhood;
use App\Models\Street;
use App\Models\Alley;

class LocationTestDataSeeder extends Seeder
{
    public function run()
    {
        // پاک کردن داده‌های قبلی (از آخر به اول برای حفظ foreign keys)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        DB::table('alleies')->truncate();
        DB::table('streets')->truncate();
        DB::table('neighborhoods')->truncate();
        DB::table('regions')->truncate();
        DB::table('cities')->truncate();
        DB::table('districts')->truncate();
        DB::table('counties')->truncate();
        DB::table('provinces')->truncate();
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        echo "✓ جداول پاک شدند\n";
        
        // استان‌های ایران (از country_id = 1)
        $provinces = [
            ['id' => 1, 'name' => 'تهران', 'country_id' => 1, 'status' => 1],
            ['id' => 2, 'name' => 'اصفهان', 'country_id' => 1, 'status' => 1],
            ['id' => 3, 'name' => 'خراسان رضوی', 'country_id' => 1, 'status' => 1],
            ['id' => 4, 'name' => 'فارس', 'country_id' => 1, 'status' => 1],
        ];
        DB::table('provinces')->insert($provinces);
        echo "✓ " . count($provinces) . " استان اضافه شد\n";
        
        // شهرستان‌ها
        $counties = [
            // شهرستان‌های استان تهران
            ['id' => 1, 'name' => 'شهرستان تهران', 'province_id' => 1],
            ['id' => 2, 'name' => 'شهرستان شمیرانات', 'province_id' => 1],
            ['id' => 3, 'name' => 'شهرستان ری', 'province_id' => 1],
            
            // شهرستان‌های استان اصفهان
            ['id' => 4, 'name' => 'شهرستان اصفهان', 'province_id' => 2],
            ['id' => 5, 'name' => 'شهرستان کاشان', 'province_id' => 2],
            
            // شهرستان‌های خراسان رضوی
            ['id' => 6, 'name' => 'شهرستان مشهد', 'province_id' => 3],
            ['id' => 7, 'name' => 'شهرستان نیشابور', 'province_id' => 3],
            
            // شهرستان‌های فارس
            ['id' => 8, 'name' => 'شهرستان شیراز', 'province_id' => 4],
        ];
        DB::table('counties')->insert($counties);
        echo "✓ " . count($counties) . " شهرستان اضافه شد\n";
        
        // بخش‌ها
        $districts = [
            // بخش‌های شهرستان تهران
            ['id' => 1, 'name' => 'بخش مرکزی', 'county_id' => 1],
            ['id' => 2, 'name' => 'بخش کن', 'county_id' => 1],
            
            // بخش‌های شهرستان شمیرانات
            ['id' => 3, 'name' => 'بخش لواسانات', 'county_id' => 2],
            
            // بخش‌های شهرستان اصفهان
            ['id' => 4, 'name' => 'بخش مرکزی', 'county_id' => 4],
            
            // بخش‌های شهرستان مشهد
            ['id' => 5, 'name' => 'بخش مرکزی', 'county_id' => 6],
            
            // بخش‌های شهرستان شیراز
            ['id' => 6, 'name' => 'بخش مرکزی', 'county_id' => 8],
        ];
        DB::table('districts')->insert($districts);
        echo "✓ " . count($districts) . " بخش اضافه شد\n";
        
        // شهرها / دهستان‌ها
        $cities = [
            // شهرهای بخش مرکزی تهران
            ['id' => 1, 'name' => 'تهران', 'district_id' => 1],
            ['id' => 2, 'name' => 'شهرک غرب', 'district_id' => 1],
            
            // شهرهای بخش کن
            ['id' => 3, 'name' => 'کن', 'district_id' => 2],
            
            // شهرهای بخش لواسانات
            ['id' => 4, 'name' => 'لواسان', 'district_id' => 3],
            
            // شهرهای اصفهان
            ['id' => 5, 'name' => 'اصفهان', 'district_id' => 4],
            
            // شهرهای مشهد
            ['id' => 6, 'name' => 'مشهد', 'district_id' => 5],
            
            // شهرهای شیراز
            ['id' => 7, 'name' => 'شیراز', 'district_id' => 6],
        ];
        DB::table('cities')->insert($cities);
        echo "✓ " . count($cities) . " شهر اضافه شد\n";
        
        // مناطق / روستاها
        $regions = [
            // مناطق شهر تهران
            ['id' => 1, 'name' => 'منطقه 1', 'city_id' => 1],
            ['id' => 2, 'name' => 'منطقه 2', 'city_id' => 1],
            ['id' => 3, 'name' => 'منطقه 3', 'city_id' => 1],
            ['id' => 4, 'name' => 'منطقه 6', 'city_id' => 1],
            ['id' => 5, 'name' => 'منطقه 21', 'city_id' => 1],
            
            // مناطق اصفهان
            ['id' => 6, 'name' => 'منطقه 1', 'city_id' => 5],
            ['id' => 7, 'name' => 'منطقه 2', 'city_id' => 5],
            
            // مناطق مشهد
            ['id' => 8, 'name' => 'منطقه 1', 'city_id' => 6],
            
            // مناطق شیراز
            ['id' => 9, 'name' => 'منطقه 1', 'city_id' => 7],
        ];
        DB::table('regions')->insert($regions);
        echo "✓ " . count($regions) . " منطقه اضافه شد\n";
        
        // محله‌ها
        $neighborhoods = [
            // محله‌های منطقه 1 تهران
            ['id' => 1, 'name' => 'تجریش', 'region_id' => 1],
            ['id' => 2, 'name' => 'نیاوران', 'region_id' => 1],
            ['id' => 3, 'name' => 'فرمانیه', 'region_id' => 1],
            
            // محله‌های منطقه 2 تهران
            ['id' => 4, 'name' => 'ونک', 'region_id' => 2],
            ['id' => 5, 'name' => 'سعادت آباد', 'region_id' => 2],
            ['id' => 6, 'name' => 'شهرک غرب', 'region_id' => 2],
            
            // محله‌های منطقه 3 تهران
            ['id' => 7, 'name' => 'پونک', 'region_id' => 3],
            ['id' => 8, 'name' => 'شهرک آزادی', 'region_id' => 3],
            
            // محله‌های منطقه 6 تهران
            ['id' => 9, 'name' => 'یوسف آباد', 'region_id' => 4],
            ['id' => 10, 'name' => 'پارک وی', 'region_id' => 4],
            
            // محله‌های اصفهان
            ['id' => 11, 'name' => 'نقش جهان', 'region_id' => 6],
            ['id' => 12, 'name' => 'جلفا', 'region_id' => 6],
            
            // محله‌های مشهد
            ['id' => 13, 'name' => 'خیام', 'region_id' => 8],
            ['id' => 14, 'name' => 'احمدآباد', 'region_id' => 8],
            
            // محله‌های شیراز
            ['id' => 15, 'name' => 'زند', 'region_id' => 9],
            ['id' => 16, 'name' => 'گلستان', 'region_id' => 9],
        ];
        DB::table('neighborhoods')->insert($neighborhoods);
        echo "✓ " . count($neighborhoods) . " محله اضافه شد\n";
        
        // خیابان‌ها (اختیاری)
        $streets = [
            // خیابان‌های تجریش
            ['id' => 1, 'name' => 'خیابان ولیعصر', 'neighborhood_id' => 1],
            ['id' => 2, 'name' => 'خیابان شریعتی', 'neighborhood_id' => 1],
            
            // خیابان‌های ونک
            ['id' => 3, 'name' => 'خیابان ونک', 'neighborhood_id' => 4],
            ['id' => 4, 'name' => 'خیابان ملاصدرا', 'neighborhood_id' => 4],
            
            // خیابان‌های یوسف آباد
            ['id' => 5, 'name' => 'خیابان انقلاب', 'neighborhood_id' => 9],
            ['id' => 6, 'name' => 'خیابان آزادی', 'neighborhood_id' => 9],
        ];
        DB::table('streets')->insert($streets);
        echo "✓ " . count($streets) . " خیابان اضافه شد\n";
        
        // کوچه‌ها (اختیاری)
        $alleys = [
            // کوچه‌های خیابان ولیعصر
            ['id' => 1, 'name' => 'کوچه شماره 1', 'street_id' => 1],
            ['id' => 2, 'name' => 'کوچه شماره 2', 'street_id' => 1],
            
            // کوچه‌های خیابان ونک
            ['id' => 3, 'name' => 'کوچه شماره 5', 'street_id' => 3],
            ['id' => 4, 'name' => 'کوچه شماره 7', 'street_id' => 3],
            
            // کوچه‌های خیابان انقلاب
            ['id' => 5, 'name' => 'کوچه شماره 10', 'street_id' => 5],
        ];
        DB::table('alleies')->insert($alleys);
        echo "✓ " . count($alleys) . " کوچه اضافه شد\n";
        
        echo "\n✅ تمام داده‌های تست با موفقیت اضافه شدند!\n";
        echo "📊 خلاصه:\n";
        echo "   - " . count($provinces) . " استان\n";
        echo "   - " . count($counties) . " شهرستان\n";
        echo "   - " . count($sections) . " بخش\n";
        echo "   - " . count($cities) . " شهر\n";
        echo "   - " . count($regions) . " منطقه\n";
        echo "   - " . count($neighborhoods) . " محله\n";
        echo "   - " . count($streets) . " خیابان\n";
        echo "   - " . count($alleys) . " کوچه\n";
    }
}
