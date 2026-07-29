<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExperienceFieldsSeeder extends Seeder
{
    public function run(): void
    {
        // =============================================
        // 1. رکوردهای موجود (با حفظ IDهای قبلی)
        // =============================================
        DB::table('experience_fields')->insert([
            ['id' => 1, 'name' => 'علوم پایه', 'parent_id' => null, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 2, 'name' => 'ریاضیات', 'parent_id' => 1, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 3, 'name' => 'ریاضیات محض', 'parent_id' => 2, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 4, 'name' => 'ریاضیات کاربردی', 'parent_id' => 2, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 5, 'name' => 'آمار و احتمال', 'parent_id' => 2, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 6, 'name' => 'فیزیک', 'parent_id' => 1, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 7, 'name' => 'فیزیک نظری', 'parent_id' => 6, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 8, 'name' => 'فیزیک تجربی', 'parent_id' => 6, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 9, 'name' => 'فیزیک کاربردی', 'parent_id' => 6, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 10, 'name' => 'شیمی', 'parent_id' => 1, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 11, 'name' => 'شیمی آلی', 'parent_id' => 10, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 12, 'name' => 'شیمی معدنی', 'parent_id' => 10, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 13, 'name' => 'شیمی فیزیک', 'parent_id' => 10, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 14, 'name' => 'زیست‌شناسی', 'parent_id' => 1, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 15, 'name' => 'زیست‌شناسی سلولی و مولکولی', 'parent_id' => 14, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 16, 'name' => 'زیست‌شناسی محیطی', 'parent_id' => 14, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 17, 'name' => 'زیست‌فناوری', 'parent_id' => 14, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 18, 'name' => 'علوم زمین', 'parent_id' => 1, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 19, 'name' => 'زمین‌شناسی', 'parent_id' => 18, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 20, 'name' => 'هواشناسی و اقلیم‌شناسی', 'parent_id' => 18, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 21, 'name' => 'اقیانوس‌شناسی', 'parent_id' => 18, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 22, 'name' => 'علوم کامپیوتر', 'parent_id' => 1, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 23, 'name' => 'نظریه محاسبات', 'parent_id' => 22, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 24, 'name' => 'علوم داده', 'parent_id' => 22, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 25, 'name' => 'هوش مصنوعی', 'parent_id' => 22, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 26, 'name' => 'علوم مهندسی', 'parent_id' => null, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 27, 'name' => 'مهندسی برق و الکترونیک', 'parent_id' => 26, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 28, 'name' => 'سیستم‌های قدرت', 'parent_id' => 27, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 29, 'name' => 'الکترونیک و مدارها', 'parent_id' => 27, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 30, 'name' => 'مخابرات', 'parent_id' => 27, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 31, 'name' => 'مهندسی مکانیک', 'parent_id' => 26, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 32, 'name' => 'طراحی مکانیکی', 'parent_id' => 31, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 33, 'name' => 'انرژی و حرارت', 'parent_id' => 31, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 34, 'name' => 'مکانیک سیالات', 'parent_id' => 31, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 35, 'name' => 'مهندسی عمران', 'parent_id' => 26, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 36, 'name' => 'سازه', 'parent_id' => 35, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 37, 'name' => 'مهندسی آب', 'parent_id' => 35, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 38, 'name' => 'حمل و نقل', 'parent_id' => 35, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 39, 'name' => 'مهندسی کامپیوتر', 'parent_id' => 26, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 40, 'name' => 'نرم‌افزار', 'parent_id' => 39, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 41, 'name' => 'سخت‌افزار', 'parent_id' => 39, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 42, 'name' => 'هوش مصنوعی و یادگیری ماشین', 'parent_id' => 39, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 43, 'name' => 'مهندسی مواد', 'parent_id' => 26, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 44, 'name' => 'متالورژی', 'parent_id' => 43, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 45, 'name' => 'سرامیک و پلیمر', 'parent_id' => 43, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 46, 'name' => 'مهندسی شیمی', 'parent_id' => 26, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 47, 'name' => 'فرآیندهای شیمیایی', 'parent_id' => 46, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 48, 'name' => 'مهندسی پلیمر', 'parent_id' => 46, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 49, 'name' => 'مهندسی هوافضا', 'parent_id' => 26, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 50, 'name' => 'طراحی هواپیما', 'parent_id' => 49, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 51, 'name' => 'سیستم‌های فضایی', 'parent_id' => 49, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 52, 'name' => 'علوم انسانی و اجتماعی', 'parent_id' => null, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 53, 'name' => 'علوم اجتماعی', 'parent_id' => 52, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 54, 'name' => 'جامعه‌شناسی', 'parent_id' => 53, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 55, 'name' => 'روان‌شناسی', 'parent_id' => 53, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 56, 'name' => 'علوم ارتباطات', 'parent_id' => 53, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 57, 'name' => 'علوم اقتصادی', 'parent_id' => 52, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 58, 'name' => 'اقتصاد کلان', 'parent_id' => 57, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 59, 'name' => 'اقتصاد خرد', 'parent_id' => 57, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 60, 'name' => 'اقتصاد بین‌الملل', 'parent_id' => 57, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 61, 'name' => 'علوم تربیتی', 'parent_id' => 52, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 62, 'name' => 'آموزش و پرورش', 'parent_id' => 61, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 63, 'name' => 'مدیریت آموزشی', 'parent_id' => 61, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 64, 'name' => 'روان‌شناسی تربیتی', 'parent_id' => 61, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 65, 'name' => 'حقوق', 'parent_id' => 52, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 66, 'name' => 'حقوق عمومی', 'parent_id' => 65, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 67, 'name' => 'حقوق خصوصی', 'parent_id' => 65, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 68, 'name' => 'حقوق بین‌الملل', 'parent_id' => 65, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 69, 'name' => 'فلسفه', 'parent_id' => 52, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 70, 'name' => 'فلسفه علم', 'parent_id' => 69, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 71, 'name' => 'فلسفه اخلاق', 'parent_id' => 69, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 72, 'name' => 'مطالعات فرهنگی', 'parent_id' => 52, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 73, 'name' => 'مطالعات رسانه', 'parent_id' => 72, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 74, 'name' => 'مطالعات جنسیت', 'parent_id' => 72, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 75, 'name' => 'علوم پزشکی و سلامت', 'parent_id' => null, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 76, 'name' => 'پزشکی', 'parent_id' => 75, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 77, 'name' => 'پزشکی عمومی', 'parent_id' => 76, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 78, 'name' => 'تخصص‌های پزشکی', 'parent_id' => 76, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 79, 'name' => 'پزشکی مولکولی', 'parent_id' => 76, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 80, 'name' => 'داروسازی', 'parent_id' => 75, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 81, 'name' => 'داروسازی بالینی', 'parent_id' => 80, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 82, 'name' => 'شیمی دارویی', 'parent_id' => 80, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 83, 'name' => 'بهداشت', 'parent_id' => 75, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 84, 'name' => 'بهداشت عمومی', 'parent_id' => 83, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 85, 'name' => 'تغذیه', 'parent_id' => 83, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 86, 'name' => 'کاردرمانی', 'parent_id' => 83, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 87, 'name' => 'علوم کشاورزی', 'parent_id' => null, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 88, 'name' => 'زراعت', 'parent_id' => 87, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 89, 'name' => 'گیاهان زراعی', 'parent_id' => 88, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 90, 'name' => 'باغبانی', 'parent_id' => 88, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 91, 'name' => 'دامپروری', 'parent_id' => 87, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 92, 'name' => 'تغذیه دام', 'parent_id' => 91, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 93, 'name' => 'ژنتیک دام', 'parent_id' => 91, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 94, 'name' => 'علوم خاک', 'parent_id' => 87, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 95, 'name' => 'مدیریت خاک', 'parent_id' => 94, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 96, 'name' => 'شیمی خاک', 'parent_id' => 94, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 97, 'name' => 'هنر و معماری', 'parent_id' => null, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 98, 'name' => 'هنرهای تجسمی', 'parent_id' => 97, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 99, 'name' => 'نقاشی', 'parent_id' => 98, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 100, 'name' => 'مجسمه‌سازی', 'parent_id' => 98, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 101, 'name' => 'معماری', 'parent_id' => 97, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 102, 'name' => 'معماری داخلی', 'parent_id' => 101, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 103, 'name' => 'معماری منظر', 'parent_id' => 101, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 104, 'name' => 'طراحی صنعتی', 'parent_id' => 97, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 105, 'name' => 'طراحی محصول', 'parent_id' => 104, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 106, 'name' => 'طراحی پایدار', 'parent_id' => 104, 'status' => 1, 'created_at' => null, 'updated_at' => null],
            ['id' => 310, 'name' => 'محاسبات کوانتومی', 'parent_id' => 22, 'status' => 1, 'created_at' => '2025-04-13 03:51:37', 'updated_at' => '2025-06-10 06:47:47'],
            ['id' => 311, 'name' => 'جبر و معادله', 'parent_id' => 2, 'status' => 1, 'created_at' => '2025-04-16 11:05:31', 'updated_at' => '2025-04-16 11:05:31'],
            ['id' => 312, 'name' => 'ادبیات', 'parent_id' => 52, 'status' => 1, 'created_at' => '2025-05-03 03:44:14', 'updated_at' => '2025-05-03 03:44:14'],
            ['id' => 313, 'name' => 'ادبیات فارسی', 'parent_id' => 312, 'status' => 1, 'created_at' => '2025-05-03 03:51:30', 'updated_at' => '2025-05-03 03:51:30'],
        ]);

        // =============================================
        // 2. زیرشاخه‌های جدید برای تکمیل ساختار سه‌سطحی
        //    (id از 321 به بعد)
        // =============================================
        DB::table('experience_fields')->insert([
            // --- زیرشاخه‌های نظریه محاسبات (id=23) ---
            ['id' => 321, 'name' => 'نظریه پیچیدگی', 'parent_id' => 23, 'status' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 322, 'name' => 'نظریه خودکارها', 'parent_id' => 23, 'status' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 323, 'name' => 'زبان‌های صوری', 'parent_id' => 23, 'status' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 324, 'name' => 'محاسبه‌پذیری', 'parent_id' => 23, 'status' => 1, 'created_at' => now(), 'updated_at' => now()],

            // --- زیرشاخه‌های علوم داده (id=24) ---
            ['id' => 325, 'name' => 'تحلیل داده', 'parent_id' => 24, 'status' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 326, 'name' => 'داده‌کاوی', 'parent_id' => 24, 'status' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 327, 'name' => 'یادگیری آماری', 'parent_id' => 24, 'status' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 328, 'name' => 'پایگاه داده‌های بزرگ', 'parent_id' => 24, 'status' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 329, 'name' => 'بصری‌سازی داده', 'parent_id' => 24, 'status' => 1, 'created_at' => now(), 'updated_at' => now()],

            // --- زیرشاخه‌های هوش مصنوعی (id=25) ---
            ['id' => 330, 'name' => 'یادگیری ماشین', 'parent_id' => 25, 'status' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 331, 'name' => 'پردازش زبان طبیعی', 'parent_id' => 25, 'status' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 332, 'name' => 'بینایی کامپیوتر', 'parent_id' => 25, 'status' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 333, 'name' => 'رباتیک', 'parent_id' => 25, 'status' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 334, 'name' => 'سیستم‌های خبره', 'parent_id' => 25, 'status' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 335, 'name' => 'یادگیری عمیق', 'parent_id' => 25, 'status' => 1, 'created_at' => now(), 'updated_at' => now()],

            // --- زیرشاخه‌های محاسبات کوانتومی (id=310) ---
            ['id' => 336, 'name' => 'الگوریتم‌های کوانتومی', 'parent_id' => 310, 'status' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 337, 'name' => 'رمزنگاری کوانتومی', 'parent_id' => 310, 'status' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 338, 'name' => 'فیزیک کوانتومی محاسباتی', 'parent_id' => 310, 'status' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 339, 'name' => 'سخت‌افزار کوانتومی', 'parent_id' => 310, 'status' => 1, 'created_at' => now(), 'updated_at' => now()],

            // --- زیرشاخه‌های فلسفه (id=69) برای تکمیل بیشتر ---
            ['id' => 340, 'name' => 'فلسفه تحلیلی', 'parent_id' => 69, 'status' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 341, 'name' => 'فلسفه قاره‌ای', 'parent_id' => 69, 'status' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 342, 'name' => 'فلسفه ذهن', 'parent_id' => 69, 'status' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 343, 'name' => 'فلسفه دین', 'parent_id' => 69, 'status' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 344, 'name' => 'فلسفه سیاسی', 'parent_id' => 69, 'status' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}