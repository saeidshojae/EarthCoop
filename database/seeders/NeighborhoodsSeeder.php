<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NeighborhoodsSeeder extends Seeder
{
    public function run(): void
    {
        // حذف تمام رکوردهای قبلی جدول
        DB::table('neighborhoods')->delete();

        // درج محلات مناطق ۲۲ گانه تهران (شماره‌گذاری ID از ۱)
        DB::table('neighborhoods')->insert([
            // ============================================
            // منطقه ۱ (parent_id = 400)
            // ============================================
            ['id' => 1, 'name' => 'ازگل', 'parent_id' => 400, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 2, 'name' => 'اقدسیه', 'parent_id' => 400, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 3, 'name' => 'الهیه', 'parent_id' => 400, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 4, 'name' => 'امامزاده قاسم', 'parent_id' => 400, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 5, 'name' => 'اوین', 'parent_id' => 400, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 6, 'name' => 'باغ فردوس', 'parent_id' => 400, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 7, 'name' => 'تجریش', 'parent_id' => 400, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 8, 'name' => 'جماران', 'parent_id' => 400, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 9, 'name' => 'چیذر', 'parent_id' => 400, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 10, 'name' => 'دارآباد', 'parent_id' => 400, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 11, 'name' => 'دربند', 'parent_id' => 400, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 12, 'name' => 'درکه', 'parent_id' => 400, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 13, 'name' => 'دزاشیب', 'parent_id' => 400, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 14, 'name' => 'جوزستان', 'parent_id' => 400, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 15, 'name' => 'زعفرانیه', 'parent_id' => 400, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 16, 'name' => 'سوهانک', 'parent_id' => 400, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 17, 'name' => 'شهرک نفت', 'parent_id' => 400, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 18, 'name' => 'شهرک محلاتی', 'parent_id' => 400, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 19, 'name' => 'فرمانیه', 'parent_id' => 400, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 20, 'name' => 'فرشته', 'parent_id' => 400, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 21, 'name' => 'قیطریه', 'parent_id' => 400, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 22, 'name' => 'کاشانک', 'parent_id' => 400, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 23, 'name' => 'کامرانیه', 'parent_id' => 400, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 24, 'name' => 'محمودیه', 'parent_id' => 400, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 25, 'name' => 'نیاوران', 'parent_id' => 400, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 26, 'name' => 'ولنجک', 'parent_id' => 400, 'status' => 1, 'created_at' => null, 'updated_at' => null],

            // ============================================
            // منطقه ۲ (parent_id = 411)
            // ============================================
            ['id' => 27, 'name' => 'برق آلستوم', 'parent_id' => 411, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 28, 'name' => 'تهران ویلا', 'parent_id' => 411, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 29, 'name' => 'ستارخان', 'parent_id' => 411, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 30, 'name' => 'سعادت آباد', 'parent_id' => 411, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 31, 'name' => 'شهرک غرب', 'parent_id' => 411, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 32, 'name' => 'شهرک مخابرات', 'parent_id' => 411, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 33, 'name' => 'شهرآرا', 'parent_id' => 411, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 34, 'name' => 'صادقیه', 'parent_id' => 411, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 35, 'name' => 'طرشت', 'parent_id' => 411, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 36, 'name' => 'فرحزاد', 'parent_id' => 411, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 37, 'name' => 'گیشا', 'parent_id' => 411, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 38, 'name' => 'همایون شهر', 'parent_id' => 411, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 39, 'name' => 'مرزداران', 'parent_id' => 411, 'status' => 1, 'created_at' => null, 'updated_at' => null],

            // ============================================
            // منطقه ۳ (parent_id = 415)
            // ============================================
            ['id' => 40, 'name' => 'اختیاریه', 'parent_id' => 415, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 41, 'name' => 'پاسداران', 'parent_id' => 415, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 42, 'name' => 'دروس', 'parent_id' => 415, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 43, 'name' => 'دولت', 'parent_id' => 415, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 44, 'name' => 'دیباجی', 'parent_id' => 415, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 45, 'name' => 'جردن', 'parent_id' => 415, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 46, 'name' => 'ولیعصر', 'parent_id' => 415, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 47, 'name' => 'سیدخندان', 'parent_id' => 415, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 48, 'name' => 'ظفر', 'parent_id' => 415, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 49, 'name' => 'قلهک', 'parent_id' => 415, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 50, 'name' => 'میرداماد', 'parent_id' => 415, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 51, 'name' => 'ونک', 'parent_id' => 415, 'status' => 1, 'created_at' => null, 'updated_at' => null],

            // ============================================
            // منطقه ۴ (parent_id = 416)
            // ============================================
            ['id' => 52, 'name' => 'بلوار پروین', 'parent_id' => 416, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 53, 'name' => 'تهرانپارس', 'parent_id' => 416, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 54, 'name' => 'حکیمیه', 'parent_id' => 416, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 55, 'name' => 'سراج', 'parent_id' => 416, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 56, 'name' => 'شمس آباد', 'parent_id' => 416, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 57, 'name' => 'مجیدیه', 'parent_id' => 416, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 58, 'name' => 'شمیران نو', 'parent_id' => 416, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 59, 'name' => 'علم و صنعت', 'parent_id' => 416, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 60, 'name' => 'فرجام', 'parent_id' => 416, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 61, 'name' => 'قنات کوثر', 'parent_id' => 416, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 62, 'name' => 'لویزان', 'parent_id' => 416, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 63, 'name' => 'شیان', 'parent_id' => 416, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 64, 'name' => 'مهران', 'parent_id' => 416, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 65, 'name' => 'نارمک', 'parent_id' => 416, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 66, 'name' => 'هروی', 'parent_id' => 416, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 67, 'name' => 'هنگام', 'parent_id' => 416, 'status' => 1, 'created_at' => null, 'updated_at' => null],

            // ============================================
            // منطقه ۵ (parent_id = 417)
            // ============================================
            ['id' => 68, 'name' => 'آیت الله کاشانی', 'parent_id' => 417, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 69, 'name' => 'اشرفی اصفهانی', 'parent_id' => 417, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 70, 'name' => 'باغ فیض', 'parent_id' => 417, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 71, 'name' => 'بلوار فردوس', 'parent_id' => 417, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 72, 'name' => 'پونک', 'parent_id' => 417, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 73, 'name' => 'جنت آباد', 'parent_id' => 417, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 74, 'name' => 'حصارک', 'parent_id' => 417, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 75, 'name' => 'سازمان برنامه', 'parent_id' => 417, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 76, 'name' => 'شاهین', 'parent_id' => 417, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 77, 'name' => 'شهران', 'parent_id' => 417, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 78, 'name' => 'شهرزیبا', 'parent_id' => 417, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 79, 'name' => 'شهرک آپادانا', 'parent_id' => 417, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 80, 'name' => 'شهرک اکباتان', 'parent_id' => 417, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 81, 'name' => 'شهرک اندیشه', 'parent_id' => 417, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 82, 'name' => 'شهرک پرواز', 'parent_id' => 417, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 83, 'name' => 'شهرک کوهسار', 'parent_id' => 417, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 84, 'name' => 'شهرک نفت', 'parent_id' => 417, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 85, 'name' => 'شهرک والفجر', 'parent_id' => 417, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 86, 'name' => 'کن', 'parent_id' => 417, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 87, 'name' => 'کنکوی', 'parent_id' => 417, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 88, 'name' => 'ارمکوی', 'parent_id' => 417, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 89, 'name' => 'بیمه', 'parent_id' => 417, 'status' => 1, 'created_at' => null, 'updated_at' => null],

            // ============================================
            // منطقه ۶ (parent_id = 418)
            // ============================================
            ['id' => 90, 'name' => 'آرژانتین', 'parent_id' => 418, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 91, 'name' => 'ساعی', 'parent_id' => 418, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 92, 'name' => 'امیرآباد', 'parent_id' => 418, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 93, 'name' => 'ایرانشهر', 'parent_id' => 418, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 94, 'name' => 'بهجت آباد', 'parent_id' => 418, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 95, 'name' => 'پارک لاله', 'parent_id' => 418, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 96, 'name' => 'جنت', 'parent_id' => 418, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 97, 'name' => 'رفعت', 'parent_id' => 418, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 98, 'name' => 'جمالزاده', 'parent_id' => 418, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 99, 'name' => 'دانشگاه تهران', 'parent_id' => 418, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 100, 'name' => 'شریعتی', 'parent_id' => 418, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 101, 'name' => 'شیراز', 'parent_id' => 418, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 102, 'name' => 'عباس آباد', 'parent_id' => 418, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 103, 'name' => 'فاطمی', 'parent_id' => 418, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 104, 'name' => 'قائم مقام', 'parent_id' => 418, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 105, 'name' => 'سنائی', 'parent_id' => 418, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 106, 'name' => 'قزل قلعه', 'parent_id' => 418, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 107, 'name' => 'کشاورز غربی', 'parent_id' => 418, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 108, 'name' => 'کریمخان', 'parent_id' => 418, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 109, 'name' => 'گاندی', 'parent_id' => 418, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 110, 'name' => 'میدان جهاد', 'parent_id' => 418, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 111, 'name' => 'میدان ولیعصر', 'parent_id' => 418, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 112, 'name' => 'نصرتی', 'parent_id' => 418, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 113, 'name' => 'وسف آباد', 'parent_id' => 418, 'status' => 1, 'created_at' => null, 'updated_at' => null],

            // ============================================
            // منطقه ۷ (parent_id = 419)
            // ============================================
            ['id' => 114, 'name' => 'اجاره دار', 'parent_id' => 419, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 115, 'name' => 'ارامنه', 'parent_id' => 419, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 116, 'name' => 'امجدیه', 'parent_id' => 419, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 117, 'name' => 'خاقانی', 'parent_id' => 419, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 118, 'name' => 'باغ صبا', 'parent_id' => 419, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 119, 'name' => 'سهروردی', 'parent_id' => 419, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 120, 'name' => 'بهار', 'parent_id' => 419, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 121, 'name' => 'حشمتیه', 'parent_id' => 419, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 122, 'name' => 'خواجه نصیر', 'parent_id' => 419, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 123, 'name' => 'حقوقی', 'parent_id' => 419, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 124, 'name' => 'دبستان', 'parent_id' => 419, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 125, 'name' => 'مجیدیه', 'parent_id' => 419, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 126, 'name' => 'سبلان', 'parent_id' => 419, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 127, 'name' => 'عباس آباد', 'parent_id' => 419, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 128, 'name' => 'اندیشه', 'parent_id' => 419, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 129, 'name' => 'قصر', 'parent_id' => 419, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 130, 'name' => 'کاج', 'parent_id' => 419, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 131, 'name' => 'کریمخان', 'parent_id' => 419, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 132, 'name' => 'مطهری نامجو', 'parent_id' => 419, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 133, 'name' => 'نظام آباد', 'parent_id' => 419, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 134, 'name' => 'نیلوفر', 'parent_id' => 419, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 135, 'name' => 'شهید قندی', 'parent_id' => 419, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 136, 'name' => 'هفت تیر', 'parent_id' => 419, 'status' => 1, 'created_at' => null, 'updated_at' => null],

            // ============================================
            // منطقه ۸ (parent_id = 420)
            // ============================================
            ['id' => 137, 'name' => 'تسلیحات', 'parent_id' => 420, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 138, 'name' => 'تهران پارس', 'parent_id' => 420, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 139, 'name' => 'دردشت', 'parent_id' => 420, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 140, 'name' => 'زرکش', 'parent_id' => 420, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 141, 'name' => 'فدک', 'parent_id' => 420, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 142, 'name' => 'کرمان', 'parent_id' => 420, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 143, 'name' => 'لشکر', 'parent_id' => 420, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 144, 'name' => 'مجیدیه جنوبی', 'parent_id' => 420, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 145, 'name' => 'مدائن', 'parent_id' => 420, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 146, 'name' => 'نارمک', 'parent_id' => 420, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 147, 'name' => 'وحیدیه', 'parent_id' => 420, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 148, 'name' => 'هفت حوض', 'parent_id' => 420, 'status' => 1, 'created_at' => null, 'updated_at' => null],

            // ============================================
            // منطقه ۹ (parent_id = 421)
            // ============================================
            ['id' => 149, 'name' => 'استاد معین', 'parent_id' => 421, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 150, 'name' => 'امامزاده عبدالله', 'parent_id' => 421, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 151, 'name' => 'دکتر هوشیار', 'parent_id' => 421, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 152, 'name' => 'سرآسیاب', 'parent_id' => 421, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 153, 'name' => 'مهرآباد', 'parent_id' => 421, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 154, 'name' => 'شمشیری', 'parent_id' => 421, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 155, 'name' => 'شهید دستغیب', 'parent_id' => 421, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 156, 'name' => 'فتح', 'parent_id' => 421, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 157, 'name' => 'صنعتی', 'parent_id' => 421, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 158, 'name' => 'فرودگاه مهرآباد جنوبی', 'parent_id' => 421, 'status' => 1, 'created_at' => null, 'updated_at' => null],

            // ============================================
            // منطقه ۱۰ (parent_id = 401)
            // ============================================
            ['id' => 159, 'name' => 'بریانک', 'parent_id' => 401, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 160, 'name' => 'سلیمانی تیموری', 'parent_id' => 401, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 161, 'name' => 'شبیری جی', 'parent_id' => 401, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 162, 'name' => 'هفت چنار', 'parent_id' => 401, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 163, 'name' => 'سلسبیل جنوبی', 'parent_id' => 401, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 164, 'name' => 'کارون جنوبی', 'parent_id' => 401, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 165, 'name' => 'هاشمی', 'parent_id' => 401, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 166, 'name' => 'زنجان جنوبی', 'parent_id' => 401, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 167, 'name' => 'سلسبیل شمالی', 'parent_id' => 401, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 168, 'name' => 'کارون شمالی', 'parent_id' => 401, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 169, 'name' => 'آذربایجان', 'parent_id' => 401, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 170, 'name' => 'آزادی', 'parent_id' => 401, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 171, 'name' => 'امام خمینی', 'parent_id' => 401, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 172, 'name' => 'جیحون', 'parent_id' => 401, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 173, 'name' => 'حسام الدین', 'parent_id' => 401, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 174, 'name' => 'خوش', 'parent_id' => 401, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 175, 'name' => 'دامپزشکی', 'parent_id' => 401, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 176, 'name' => 'رودکی', 'parent_id' => 401, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 177, 'name' => 'سینا', 'parent_id' => 401, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 178, 'name' => 'قصرالدشت', 'parent_id' => 401, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 179, 'name' => 'مالک اشتر', 'parent_id' => 401, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 180, 'name' => 'نواب', 'parent_id' => 401, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 181, 'name' => 'کارون', 'parent_id' => 401, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 182, 'name' => 'کمیل', 'parent_id' => 401, 'status' => 1, 'created_at' => null, 'updated_at' => null],

            // ============================================
            // منطقه ۱۱ (parent_id = 402)
            // ============================================
            ['id' => 183, 'name' => 'شیخ هادی', 'parent_id' => 402, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 184, 'name' => 'انقلاب', 'parent_id' => 402, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 185, 'name' => 'امیریه (فرهنگ)', 'parent_id' => 402, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 186, 'name' => 'فروزش', 'parent_id' => 402, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 187, 'name' => 'قلمستان', 'parent_id' => 402, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 188, 'name' => 'منیریه', 'parent_id' => 402, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 189, 'name' => 'حشمت الدوله', 'parent_id' => 402, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 190, 'name' => 'جمالزاده', 'parent_id' => 402, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 191, 'name' => 'اسکندری', 'parent_id' => 402, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 192, 'name' => 'دخانیات', 'parent_id' => 402, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 193, 'name' => 'مخصوص', 'parent_id' => 402, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 194, 'name' => 'جمهوری', 'parent_id' => 402, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 195, 'name' => 'حر', 'parent_id' => 402, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 196, 'name' => 'انبار نفت', 'parent_id' => 402, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 197, 'name' => 'آگاهی', 'parent_id' => 402, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 198, 'name' => 'راه آهن', 'parent_id' => 402, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 199, 'name' => 'عباسی', 'parent_id' => 402, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 200, 'name' => 'هلال احمر', 'parent_id' => 402, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 201, 'name' => 'ابوسعید', 'parent_id' => 402, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 202, 'name' => 'اسکندری جنوبی', 'parent_id' => 402, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 203, 'name' => 'پاستور', 'parent_id' => 402, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 204, 'name' => 'حسن آباد', 'parent_id' => 402, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 205, 'name' => 'گمرک', 'parent_id' => 402, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 206, 'name' => 'وحدت اسلامی', 'parent_id' => 402, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 207, 'name' => 'ولیعصر', 'parent_id' => 402, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 208, 'name' => 'کارگر جنوبی', 'parent_id' => 402, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 209, 'name' => 'کاشان', 'parent_id' => 402, 'status' => 1, 'created_at' => null, 'updated_at' => null],

            // ============================================
            // منطقه ۱۲ (parent_id = 403)
            // ============================================
            ['id' => 210, 'name' => 'بهارستان', 'parent_id' => 403, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 211, 'name' => 'فردوسی', 'parent_id' => 403, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 212, 'name' => 'امامزاده یحیی', 'parent_id' => 403, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 213, 'name' => 'پامنار', 'parent_id' => 403, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 214, 'name' => 'بازار', 'parent_id' => 403, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 215, 'name' => 'سنگلج', 'parent_id' => 403, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 216, 'name' => 'تختی', 'parent_id' => 403, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 217, 'name' => 'هرندی', 'parent_id' => 403, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 218, 'name' => 'آبشار', 'parent_id' => 403, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 219, 'name' => 'قیام', 'parent_id' => 403, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 220, 'name' => 'کوثر', 'parent_id' => 403, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 221, 'name' => 'ایران', 'parent_id' => 403, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 222, 'name' => 'دروازه شمیران', 'parent_id' => 403, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 223, 'name' => 'امین حضور', 'parent_id' => 403, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 224, 'name' => 'پانزده خرداد', 'parent_id' => 403, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 225, 'name' => 'پیچ شمیران', 'parent_id' => 403, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 226, 'name' => 'خراسان', 'parent_id' => 403, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 227, 'name' => 'ری', 'parent_id' => 403, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 228, 'name' => 'سعدی', 'parent_id' => 403, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 229, 'name' => 'لاله زارنو', 'parent_id' => 403, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 230, 'name' => 'مولوی', 'parent_id' => 403, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 231, 'name' => 'میدان قیام', 'parent_id' => 403, 'status' => 1, 'created_at' => null, 'updated_at' => null],

            // ============================================
            // منطقه ۱۳ (parent_id = 404)
            // ============================================
            ['id' => 232, 'name' => 'صفا', 'parent_id' => 404, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 233, 'name' => 'شهید اسدی', 'parent_id' => 404, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 234, 'name' => 'زاهد گیلانی', 'parent_id' => 404, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 235, 'name' => 'اشراقی', 'parent_id' => 404, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 236, 'name' => 'دهقان', 'parent_id' => 404, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 237, 'name' => 'نیروی هوایی', 'parent_id' => 404, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 238, 'name' => 'پیروزی', 'parent_id' => 404, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 239, 'name' => 'حافظیه', 'parent_id' => 404, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 240, 'name' => 'امامت', 'parent_id' => 404, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 241, 'name' => 'شورا', 'parent_id' => 404, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 242, 'name' => 'آشتیانی', 'parent_id' => 404, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 243, 'name' => 'زینبیه', 'parent_id' => 404, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 244, 'name' => 'سرخه حصار', 'parent_id' => 404, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 245, 'name' => 'تهران نو', 'parent_id' => 404, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 246, 'name' => 'دماوند', 'parent_id' => 404, 'status' => 1, 'created_at' => null, 'updated_at' => null],

            // ============================================
            // منطقه ۱۴ (parent_id = 405)
            // ============================================
            ['id' => 247, 'name' => 'شکوفه', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 248, 'name' => 'چهارصد دستگاه', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 249, 'name' => 'جابری', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 250, 'name' => 'دژکام', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 251, 'name' => 'شاهین', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 252, 'name' => 'مینای شمالی', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 253, 'name' => 'نیکنام', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 254, 'name' => 'آهنگران', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 255, 'name' => 'بروجردی', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 256, 'name' => 'صد دستگاه', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 257, 'name' => 'فرزانه', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 258, 'name' => 'سرآسیاب دولاب', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 259, 'name' => 'شیوا', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 260, 'name' => 'نبی اکرم (ص)', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 261, 'name' => 'شهرابی (قیام)', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 262, 'name' => 'شکیب', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 263, 'name' => 'پرستار', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 264, 'name' => 'سیزده آبان', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 265, 'name' => 'ابوذر', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 266, 'name' => 'تاکسیرانی', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 267, 'name' => 'مینای جنوبی', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 268, 'name' => 'دولاب', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 269, 'name' => 'خاوران', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 270, 'name' => 'آهنگ شرقی', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 271, 'name' => 'آهنگ', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 272, 'name' => 'قصر فیروزه', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 273, 'name' => 'اتوبان محلاتی', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 274, 'name' => 'افراسیابی شمالی', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 275, 'name' => 'اندرزگو', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 276, 'name' => 'پاسدارگمنام', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 277, 'name' => 'شهید محلاتی', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 278, 'name' => 'فلاح', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 279, 'name' => 'نبرد', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 280, 'name' => 'هفده شهریور', 'parent_id' => 405, 'status' => 1, 'created_at' => null, 'updated_at' => null],

            // ============================================
            // منطقه ۱۵ (parent_id = 406)
            // ============================================
            ['id' => 281, 'name' => 'مظاهری', 'parent_id' => 406, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 282, 'name' => 'مینابی', 'parent_id' => 406, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 283, 'name' => 'بیسیم', 'parent_id' => 406, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 284, 'name' => 'شوش', 'parent_id' => 406, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 285, 'name' => 'طیب', 'parent_id' => 406, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 286, 'name' => 'مطهری', 'parent_id' => 406, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 287, 'name' => 'ابوذر', 'parent_id' => 406, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 288, 'name' => 'هاشم آباد', 'parent_id' => 406, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 289, 'name' => 'اتابک', 'parent_id' => 406, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 290, 'name' => 'شهید بروجردی', 'parent_id' => 406, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 291, 'name' => 'کیانشهر شمالی', 'parent_id' => 406, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 292, 'name' => 'کیانشهر جنوبی', 'parent_id' => 406, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 293, 'name' => 'رضویه', 'parent_id' => 406, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 294, 'name' => 'مشیریه', 'parent_id' => 406, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 295, 'name' => 'مسعودیه', 'parent_id' => 406, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 296, 'name' => 'والفجر', 'parent_id' => 406, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 297, 'name' => 'قیامدشت', 'parent_id' => 406, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 298, 'name' => 'خاورشهر', 'parent_id' => 406, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 299, 'name' => 'آهنگ', 'parent_id' => 406, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 300, 'name' => 'اتوبان بعثت', 'parent_id' => 406, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 301, 'name' => 'افسریه', 'parent_id' => 406, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 302, 'name' => 'خاوران', 'parent_id' => 406, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 303, 'name' => 'مشیریه', 'parent_id' => 406, 'status' => 1, 'created_at' => null, 'updated_at' => null],

            // ============================================
            // منطقه ۱۶ (parent_id = 407)
            // ============================================
            ['id' => 304, 'name' => 'جوادیه', 'parent_id' => 407, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 305, 'name' => 'نازی آباد', 'parent_id' => 407, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 306, 'name' => 'خزانه', 'parent_id' => 407, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 307, 'name' => 'شهرک بعثت', 'parent_id' => 407, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 308, 'name' => 'علی آباد', 'parent_id' => 407, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 309, 'name' => 'یاخچی آباد', 'parent_id' => 407, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 310, 'name' => 'چهارصد دستگاه', 'parent_id' => 407, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 311, 'name' => 'تختی', 'parent_id' => 407, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 312, 'name' => 'باغ آذری', 'parent_id' => 407, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 313, 'name' => 'راه آهن', 'parent_id' => 407, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 314, 'name' => 'رجایی', 'parent_id' => 407, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 315, 'name' => 'هلال احمر', 'parent_id' => 407, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 316, 'name' => 'یاخچی آباد', 'parent_id' => 407, 'status' => 1, 'created_at' => null, 'updated_at' => null],

            // ============================================
            // منطقه ۱۷ (parent_id = 408)
            // ============================================
            ['id' => 317, 'name' => 'آذری', 'parent_id' => 408, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 318, 'name' => 'امامزاده حسن', 'parent_id' => 408, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 319, 'name' => 'یافت آباد', 'parent_id' => 408, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 320, 'name' => 'جلیلی', 'parent_id' => 408, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 321, 'name' => 'زهتابی', 'parent_id' => 408, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 322, 'name' => 'زمزم', 'parent_id' => 408, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 323, 'name' => 'سجاد', 'parent_id' => 408, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 324, 'name' => 'گلچین', 'parent_id' => 408, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 325, 'name' => 'وصفنارد', 'parent_id' => 408, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 326, 'name' => 'باغ خزانه', 'parent_id' => 408, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 327, 'name' => 'بلورسازی', 'parent_id' => 408, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 328, 'name' => 'مقدم', 'parent_id' => 408, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 329, 'name' => 'ابوذر', 'parent_id' => 408, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 330, 'name' => 'امام زاده حسن', 'parent_id' => 408, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 331, 'name' => 'قزوین', 'parent_id' => 408, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 332, 'name' => 'قلعه مرغی', 'parent_id' => 408, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 333, 'name' => 'میدان بهاران', 'parent_id' => 408, 'status' => 1, 'created_at' => null, 'updated_at' => null],

            // ============================================
            // منطقه ۱۸ (parent_id = 409)
            // ============================================
            ['id' => 334, 'name' => 'فردوس', 'parent_id' => 409, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 335, 'name' => 'تولیدارو', 'parent_id' => 409, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 336, 'name' => 'بهداشت', 'parent_id' => 409, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 337, 'name' => 'ولیعصر شمالی', 'parent_id' => 409, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 338, 'name' => 'رجائی', 'parent_id' => 409, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 339, 'name' => 'ولیعصر جنوبی', 'parent_id' => 409, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 340, 'name' => 'صادقیه', 'parent_id' => 409, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 341, 'name' => 'صاحب الزمان', 'parent_id' => 409, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 342, 'name' => 'یافت آباد جنوبی', 'parent_id' => 409, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 343, 'name' => 'یافت آباد شمالی', 'parent_id' => 409, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 344, 'name' => 'شاد آباد', 'parent_id' => 409, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 345, 'name' => 'هفده شهریور', 'parent_id' => 409, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 346, 'name' => 'امام خمینی', 'parent_id' => 409, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 347, 'name' => 'شمس آباد', 'parent_id' => 409, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 348, 'name' => 'خلیج فارس شمالی', 'parent_id' => 409, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 349, 'name' => 'خلیج فارس جنوبی', 'parent_id' => 409, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 350, 'name' => 'اتوبان آزادگان', 'parent_id' => 409, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 351, 'name' => 'خلیج فارس', 'parent_id' => 409, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 352, 'name' => 'سعید آباد', 'parent_id' => 409, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 353, 'name' => 'شهرک صاحب الزمان', 'parent_id' => 409, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 354, 'name' => 'شهرک ولیعصر', 'parent_id' => 409, 'status' => 1, 'created_at' => null, 'updated_at' => null],

            // ============================================
            // منطقه ۱۹ (parent_id = 410)
            // ============================================
            ['id' => 355, 'name' => 'اسفندیاری و بستان', 'parent_id' => 410, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 356, 'name' => 'بهمنیار', 'parent_id' => 410, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 357, 'name' => 'شریعتی جنوبی', 'parent_id' => 410, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 358, 'name' => 'شریعتی شمالی', 'parent_id' => 410, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 359, 'name' => 'شکوفه جنوبی', 'parent_id' => 410, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 360, 'name' => 'شکوفه شمالی', 'parent_id' => 410, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 361, 'name' => 'نعمت آباد', 'parent_id' => 410, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 362, 'name' => 'دولت خواه', 'parent_id' => 410, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 363, 'name' => 'اسماعیل آباد', 'parent_id' => 410, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 364, 'name' => 'شهید کاظمی', 'parent_id' => 410, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 365, 'name' => 'رسالت', 'parent_id' => 410, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 366, 'name' => 'خانی آباد نو', 'parent_id' => 410, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 367, 'name' => 'عبدل آباد', 'parent_id' => 410, 'status' => 1, 'created_at' => null, 'updated_at' => null],

            // ============================================
            // منطقه ۲۰ (parent_id = 412)
            // ============================================
            ['id' => 368, 'name' => 'اقدسیه', 'parent_id' => 412, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 369, 'name' => 'صفائیه', 'parent_id' => 412, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 370, 'name' => 'ظهیر آباد', 'parent_id' => 412, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 371, 'name' => 'غیوری', 'parent_id' => 412, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 372, 'name' => 'جوانمرد', 'parent_id' => 412, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 373, 'name' => 'حمزه آباد', 'parent_id' => 412, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 374, 'name' => 'دیلمان', 'parent_id' => 412, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 375, 'name' => 'فیروزآبادی', 'parent_id' => 412, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 376, 'name' => 'منصوریه', 'parent_id' => 412, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 377, 'name' => '۱۳ آبان', 'parent_id' => 412, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 378, 'name' => 'دولت آباد', 'parent_id' => 412, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 379, 'name' => 'شهادت', 'parent_id' => 412, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 380, 'name' => 'استخر', 'parent_id' => 412, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 381, 'name' => 'بهشتی', 'parent_id' => 412, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 382, 'name' => 'سرتخت', 'parent_id' => 412, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 383, 'name' => 'علائین', 'parent_id' => 412, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 384, 'name' => 'نفر آباد', 'parent_id' => 412, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 385, 'name' => 'ولی آباد', 'parent_id' => 412, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 386, 'name' => 'امین آباد', 'parent_id' => 412, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 387, 'name' => 'تقی آباد', 'parent_id' => 412, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 388, 'name' => 'نظامی', 'parent_id' => 412, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 389, 'name' => 'عباس آباد', 'parent_id' => 412, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 390, 'name' => 'کهریزک', 'parent_id' => 412, 'status' => 1, 'created_at' => null, 'updated_at' => null],

            // ============================================
            // منطقه ۲۱ (parent_id = 413)
            // ============================================
            ['id' => 391, 'name' => 'شهرک دریا', 'parent_id' => 413, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 392, 'name' => 'تهرانسر', 'parent_id' => 413, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 393, 'name' => 'تهرانسر غربی', 'parent_id' => 413, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 394, 'name' => 'تهرانسر مرکزی', 'parent_id' => 413, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 395, 'name' => 'تهرانسر شرقی', 'parent_id' => 413, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 396, 'name' => 'باشگاه نفت', 'parent_id' => 413, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 397, 'name' => 'شهرک پاسداران', 'parent_id' => 413, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 398, 'name' => 'شهرک آزادی', 'parent_id' => 413, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 399, 'name' => 'شهرک فرهنگیان', 'parent_id' => 413, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 400, 'name' => 'شهرک استقلال', 'parent_id' => 413, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 401, 'name' => 'شهرک دانشگاه', 'parent_id' => 413, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 402, 'name' => 'چیتگر شمالی', 'parent_id' => 413, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 403, 'name' => 'چیتگر جنوبی (شهرک ۲۲ بهمن)', 'parent_id' => 413, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 404, 'name' => 'ویلا شهر', 'parent_id' => 413, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 405, 'name' => 'وردآورد', 'parent_id' => 413, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 406, 'name' => 'شهرک غزالی', 'parent_id' => 413, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 407, 'name' => 'شهرک شهرداری', 'parent_id' => 413, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 408, 'name' => 'اتوبان تهران کرج', 'parent_id' => 413, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 409, 'name' => 'بزرگراه فتح', 'parent_id' => 413, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 410, 'name' => 'شهرک دانشگاه شریف', 'parent_id' => 413, 'status' => 1, 'created_at' => null, 'updated_at' => null],

            // ============================================
            // منطقه ۲۲ (parent_id = 414)
            // ============================================
            ['id' => 411, 'name' => 'دهکده المپیک', 'parent_id' => 414, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 412, 'name' => 'زیبادشت بالا', 'parent_id' => 414, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 413, 'name' => 'گلستان شرقی', 'parent_id' => 414, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 414, 'name' => 'زیبادشت پائین', 'parent_id' => 414, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 415, 'name' => 'شریف', 'parent_id' => 414, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 416, 'name' => 'گلستان غربی', 'parent_id' => 414, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 417, 'name' => 'امید', 'parent_id' => 414, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 418, 'name' => 'دژبان', 'parent_id' => 414, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 419, 'name' => 'شهید باقری', 'parent_id' => 414, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 420, 'name' => 'آزادشهر', 'parent_id' => 414, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 421, 'name' => 'پیکان شهر', 'parent_id' => 414, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 422, 'name' => 'دریاچه چیتگر', 'parent_id' => 414, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 423, 'name' => 'شهرک راه آهن', 'parent_id' => 414, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 424, 'name' => 'شهرک گلستان', 'parent_id' => 414, 'status' => 1, 'created_at' => null, 'updated_at' => null],
        ]);
    }
}