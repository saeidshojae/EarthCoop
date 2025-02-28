<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Specialization;

class SpecializationSeeder extends Seeder
{
    public function run()
    {
        $specializations = [
            // شاخه‌های اصلی
            ['title' => 'مهندسی', 'parent_id' => null, 'level' => 1, 'job_field_id' => 1],
            ['title' => 'پزشکی', 'parent_id' => null, 'level' => 1, 'job_field_id' => 2],
            ['title' => 'علوم انسانی', 'parent_id' => null, 'level' => 1, 'job_field_id' => 3],
            ['title' => 'علوم پایه', 'parent_id' => null, 'level' => 1, 'job_field_id' => 4],
            ['title' => 'علوم اجتماعی', 'parent_id' => null, 'level' => 1, 'job_field_id' => 5],
            ['title' => 'مدیریت و بازرگانی', 'parent_id' => null, 'level' => 1, 'job_field_id' => 6],
            ['title' => 'حقوق و قانون', 'parent_id' => null, 'level' => 1, 'job_field_id' => 7],
            ['title' => 'علوم کامپیوتر و فناوری اطلاعات', 'parent_id' => null, 'level' => 1, 'job_field_id' => 8],
            ['title' => 'هنر و طراحی', 'parent_id' => null, 'level' => 1, 'job_field_id' => 9],
            ['title' => 'معماری و شهرسازی', 'parent_id' => null, 'level' => 1, 'job_field_id' => 10],
            ['title' => 'آموزش و پژوهش', 'parent_id' => null, 'level' => 1, 'job_field_id' => 11],
            ['title' => 'محیط زیست و پایداری', 'parent_id' => null, 'level' => 1, 'job_field_id' => 12],
        ];

        // ایجاد شاخه‌های اصلی
        foreach ($specializations as $specialization) {
            Specialization::updateOrCreate(
                ['title' => $specialization['title']],
                $specialization
            );
        }

        // زیرشاخه‌های مهندسی
        $engineeringSubSpecializations = [
            ['title' => 'مهندسی برق', 'parent_id' => 1, 'level' => 2, 'job_field_id' => 1],
            ['title' => 'مهندسی مکانیک', 'parent_id' => 1, 'level' => 2, 'job_field_id' => 1],
            ['title' => 'مهندسی عمران', 'parent_id' => 1, 'level' => 2, 'job_field_id' => 1],
            ['title' => 'مهندسی شیمی', 'parent_id' => 1, 'level' => 2, 'job_field_id' => 1],
            ['title' => 'مهندسی صنایع', 'parent_id' => 1, 'level' => 2, 'job_field_id' => 1],
        ];

        // زیرزیرشاخه‌های مهندسی برق
        $electricalEngineeringSubSpecializations = [
            ['title' => 'الکترونیک', 'parent_id' => 13, 'level' => 3, 'job_field_id' => 1],
            ['title' => 'قدرت', 'parent_id' => 13, 'level' => 3, 'job_field_id' => 1],
            ['title' => 'کنترل', 'parent_id' => 13, 'level' => 3, 'job_field_id' => 1],
            ['title' => 'مخابرات', 'parent_id' => 13, 'level' => 3, 'job_field_id' => 1],
            ['title' => 'سیستم‌های قدرت', 'parent_id' => 13, 'level' => 3, 'job_field_id' => 1],
        ];

        // زیرزیرشاخه‌های مهندسی مکانیک
        $mechanicalEngineeringSubSpecializations = [
            ['title' => 'خودروسازی', 'parent_id' => 14, 'level' => 3, 'job_field_id' => 1],
            ['title' => 'سیالات', 'parent_id' => 14, 'level' => 3, 'job_field_id' => 1],
            ['title' => 'جامدات', 'parent_id' => 14, 'level' => 3, 'job_field_id' => 1],
            ['title' => 'طراحی محصول', 'parent_id' => 14, 'level' => 3, 'job_field_id' => 1],
            ['title' => 'سیستم‌های حرارتی', 'parent_id' => 14, 'level' => 3, 'job_field_id' => 1],
        ];

        // زیرزیرشاخه‌های مهندسی عمران
        $civilEngineeringSubSpecializations = [
            ['title' => 'سازه', 'parent_id' => 15, 'level' => 3, 'job_field_id' => 1],
            ['title' => 'راه و ترابری', 'parent_id' => 15, 'level' => 3, 'job_field_id' => 1],
            ['title' => 'آب و فاضلاب', 'parent_id' => 15, 'level' => 3, 'job_field_id' => 1],
            ['title' => 'مدیریت پروژه', 'parent_id' => 15, 'level' => 3, 'job_field_id' => 1],
            ['title' => 'مهندسی زلزله', 'parent_id' => 15, 'level' => 3, 'job_field_id' => 1],
        ];

        // زیرزیرشاخه‌های مهندسی شیمی
        $chemicalEngineeringSubSpecializations = [
            ['title' => 'فرآیند', 'parent_id' => 16, 'level' => 3, 'job_field_id' => 1],
            ['title' => 'پلیمر', 'parent_id' => 16, 'level' => 3, 'job_field_id' => 1],
            ['title' => 'نانو', 'parent_id' => 16, 'level' => 3, 'job_field_id' => 1],
            ['title' => 'بیوشیمی', 'parent_id' => 16, 'level' => 3, 'job_field_id' => 1],
            ['title' => 'مهندسی محیط زیست', 'parent_id' => 16, 'level' => 3, 'job_field_id' => 1],
        ];

        // زیرزیرشاخه‌های مهندسی صنایع
        $industrialEngineeringSubSpecializations = [
            ['title' => 'مدیریت صنعتی', 'parent_id' => 17, 'level' => 3, 'job_field_id' => 1],
            ['title' => 'برنامه‌ریزی تولید', 'parent_id' => 17, 'level' => 3, 'job_field_id' => 1],
            ['title' => 'بهینه‌سازی', 'parent_id' => 17, 'level' => 3, 'job_field_id' => 1],
            ['title' => 'مهندسی سیستم‌ها', 'parent_id' => 17, 'level' => 3, 'job_field_id' => 1],
            ['title' => 'تحلیل عملیات', 'parent_id' => 17, 'level' => 3, 'job_field_id' => 1],
        ];

        // زیرشاخه‌های پزشکی
        $medicalSubSpecializations = [
            ['title' => 'پزشکی عمومی', 'parent_id' => 2, 'level' => 2, 'job_field_id' => 2],
            ['title' => 'جراحی', 'parent_id' => 2, 'level' => 2, 'job_field_id' => 2],
            ['title' => 'دندانپزشکی', 'parent_id' => 2, 'level' => 2, 'job_field_id' => 2],
            ['title' => 'داروسازی', 'parent_id' => 2, 'level' => 2, 'job_field_id' => 2],
            ['title' => 'پرستاری', 'parent_id' => 2, 'level' => 2, 'job_field_id' => 2],
        ];

        // زیرزیرشاخه‌های پزشکی عمومی
        $generalMedicineSubSpecializations = [
            ['title' => 'داخلی', 'parent_id' => 18, 'level' => 3, 'job_field_id' => 2],
            ['title' => 'اطفال', 'parent_id' => 18, 'level' => 3, 'job_field_id' => 2],
            ['title' => 'زنان و زایمان', 'parent_id' => 18, 'level' => 3, 'job_field_id' => 2],
            ['title' => 'طب اورژانس', 'parent_id' => 18, 'level' => 3, 'job_field_id' => 2],
        ];

        // زیرزیرشاخه‌های جراحی
        $surgerySubSpecializations = [
            ['title' => 'عمومی', 'parent_id' => 19, 'level' => 3, 'job_field_id' => 2],
            ['title' => 'قلب و عروق', 'parent_id' => 19, 'level' => 3, 'job_field_id' => 2],
            ['title' => 'ارتوپدی', 'parent_id' => 19, 'level' => 3, 'job_field_id' => 2],
            ['title' => 'مغز و اعصاب', 'parent_id' => 19, 'level' => 3, 'job_field_id' => 2],
            ['title' => 'جراحی پلاستیک', 'parent_id' => 19, 'level' => 3, 'job_field_id' => 2],
        ];

        // زیرزیرشاخه‌های دندانپزشکی
        $dentistrySubSpecializations = [
            ['title' => 'عمومی', 'parent_id' => 20, 'level' => 3, 'job_field_id' => 2],
            ['title' => 'ارتودنسی', 'parent_id' => 20, 'level' => 3, 'job_field_id' => 2],
            ['title' => 'ترمیمی', 'parent_id' => 20, 'level' => 3, 'job_field_id' => 2],
            ['title' => 'جراحی دهان', 'parent_id' => 20, 'level' => 3, 'job_field_id' => 2],
            ['title' => 'دندانپزشکی زیبایی', 'parent_id' => 20, 'level' => 3, 'job_field_id' => 2],
        ];

        // زیرزیرشاخه‌های داروسازی
        $pharmacySubSpecializations = [
            ['title' => 'تولید دارو', 'parent_id' => 21, 'level' => 3, 'job_field_id' => 2],
            ['title' => 'توزیع دارو', 'parent_id' => 21, 'level' => 3, 'job_field_id' => 2],
            ['title' => 'داروسازی بالینی', 'parent_id' => 21, 'level' => 3, 'job_field_id' => 2],
            ['title' => 'داروشناسی', 'parent_id' => 21, 'level' => 3, 'job_field_id' => 2],
        ];

        // زیرزیرشاخه‌های پرستاری
        $nursingSubSpecializations = [
            ['title' => 'عمومی', 'parent_id' => 22, 'level' => 3, 'job_field_id' => 2],
            ['title' => 'تخصصی', 'parent_id' => 22, 'level' => 3, 'job_field_id' => 2],
            ['title' => 'پرستاری روانی', 'parent_id' => 22, 'level' => 3, 'job_field_id' => 2],
            ['title' => 'پرستاری اورژانس', 'parent_id' => 22, 'level' => 3, 'job_field_id' => 2],
        ];

        // زیرشاخه‌های علوم انسانی
        $humanitiesSubSpecializations = [
            ['title' => 'روانشناسی', 'parent_id' => 3, 'level' => 2, 'job_field_id' => 3],
            ['title' => 'فلسفه', 'parent_id' => 3, 'level' => 2, 'job_field_id' => 3],
            ['title' => 'زبان و ادبیات', 'parent_id' => 3, 'level' => 2, 'job_field_id' => 3],
        ];

        // زیرزیرشاخه‌های روانشناسی
        $psychologySubSpecializations = [
            ['title' => 'بالینی', 'parent_id' => 23, 'level' => 3, 'job_field_id' => 3],
            ['title' => 'مشاوره', 'parent_id' => 23, 'level' => 3, 'job_field_id' => 3],
            ['title' => 'روانشناسی اجتماعی', 'parent_id' => 23, 'level' => 3, 'job_field_id' => 3],
            ['title' => 'روانشناسی رشد', 'parent_id' => 23, 'level' => 3, 'job_field_id' => 3],
        ];

        // زیرزیرشاخه‌های فلسفه
        $philosophySubSpecializations = [
            ['title' => 'تاریخ فلسفه', 'parent_id' => 24, 'level' => 3, 'job_field_id' => 3],
            ['title' => 'فلسفه معاصر', 'parent_id' => 24, 'level' => 3, 'job_field_id' => 3],
            ['title' => 'فلسفه علم', 'parent_id' => 24, 'level' => 3, 'job_field_id' => 3],
            ['title' => 'فلسفه اخلاق', 'parent_id' => 24, 'level' => 3, 'job_field_id' => 3],
        ];

        // زیرزیرشاخه‌های زبان و ادبیات
        $literatureSubSpecializations = [
            ['title' => 'زبان‌شناسی', 'parent_id' => 25, 'level' => 3, 'job_field_id' => 3],
            ['title' => 'ادبیات تطبیقی', 'parent_id' => 25, 'level' => 3, 'job_field_id' => 3],
            ['title' => 'ترجمه', 'parent_id' => 25, 'level' => 3, 'job_field_id' => 3],
            ['title' => 'نویسندگی خلاق', 'parent_id' => 25, 'level' => 3, 'job_field_id' => 3],
        ];

        // زیرشاخه‌های علوم پایه
        $basicSciencesSubSpecializations = [
            ['title' => 'فیزیک', 'parent_id' => 4, 'level' => 2, 'job_field_id' => 4],
            ['title' => 'شیمی', 'parent_id' => 4, 'level' => 2, 'job_field_id' => 4],
            ['title' => 'ریاضی', 'parent_id' => 4, 'level' => 2, 'job_field_id' => 4],
            ['title' => 'زیست‌شناسی', 'parent_id' => 4, 'level' => 2, 'job_field_id' => 4],
        ];

        // زیرزیرشاخه‌های فیزیک
        $physicsSubSpecializations = [
            ['title' => 'فیزیک نظری', 'parent_id' => 26, 'level' => 3, 'job_field_id' => 4],
            ['title' => 'فیزیک کاربردی', 'parent_id' => 26, 'level' => 3, 'job_field_id' => 4],
        ];

        // زیرزیرشاخه‌های شیمی
        $chemistrySubSpecializations = [
            ['title' => 'شیمی آلی', 'parent_id' => 27, 'level' => 3, 'job_field_id' => 4],
            ['title' => 'شیمی معدنی', 'parent_id' => 27, 'level' => 3, 'job_field_id' => 4],
            ['title' => 'شیمی فیزیک', 'parent_id' => 27, 'level' => 3, 'job_field_id' => 4],
        ];

        // زیرزیرشاخه‌های ریاضی
        $mathematicsSubSpecializations = [
            ['title' => 'ریاضیات محض', 'parent_id' => 28, 'level' => 3, 'job_field_id' => 4],
            ['title' => 'ریاضیات کاربردی', 'parent_id' => 28, 'level' => 3, 'job_field_id' => 4],
        ];

        // زیرزیرشاخه‌های زیست‌شناسی
        $biologySubSpecializations = [
            ['title' => 'زیست‌شناسی مولکولی', 'parent_id' => 29, 'level' => 3, 'job_field_id' => 4],
            ['title' => 'زیست‌شناسی سلولی', 'parent_id' => 29, 'level' => 3, 'job_field_id' => 4],
            ['title' => 'زیست‌شناسی محیطی', 'parent_id' => 29, 'level' => 3, 'job_field_id' => 4],
        ];

        // زیرشاخه‌های علوم اجتماعی
        $socialSciencesSubSpecializations = [
            ['title' => 'جامعه‌شناسی', 'parent_id' => 5, 'level' => 2, 'job_field_id' => 5],
            ['title' => 'اقتصاد', 'parent_id' => 5, 'level' => 2, 'job_field_id' => 5],
            ['title' => 'علوم سیاسی', 'parent_id' => 5, 'level' => 2, 'job_field_id' => 5],
        ];

        // زیرزیرشاخه‌های جامعه‌شناسی
        $sociologySubSpecializations = [
            ['title' => 'جامعه‌شناسی عمومی', 'parent_id' => 30, 'level' => 3, 'job_field_id' => 5],
            ['title' => 'جامعه‌شناسی شهری', 'parent_id' => 30, 'level' => 3, 'job_field_id' => 5],
            ['title' => 'جامعه‌شناسی روستایی', 'parent_id' => 30, 'level' => 3, 'job_field_id' => 5],
        ];

        // زیرزیرشاخه‌های اقتصاد
        $economicsSubSpecializations = [
            ['title' => 'اقتصاد کلان', 'parent_id' => 31, 'level' => 3, 'job_field_id' => 5],
            ['title' => 'اقتصاد خرد', 'parent_id' => 31, 'level' => 3, 'job_field_id' => 5],
            ['title' => 'اقتصاد بین‌الملل', 'parent_id' => 31, 'level' => 3, 'job_field_id' => 5],
        ];

        // زیرزیرشاخه‌های علوم سیاسی
        $politicalScienceSubSpecializations = [
            ['title' => 'سیاست داخلی', 'parent_id' => 32, 'level' => 3, 'job_field_id' => 5],
            ['title' => 'سیاست خارجی', 'parent_id' => 32, 'level' => 3, 'job_field_id' => 5],
            ['title' => 'روابط بین‌الملل', 'parent_id' => 32, 'level' => 3, 'job_field_id' => 5],
        ];

        // زیرشاخه‌های مدیریت و بازرگانی
        $managementSubSpecializations = [
            ['title' => 'مدیریت', 'parent_id' => 6, 'level' => 2, 'job_field_id' => 6],
            ['title' => 'بازاریابی', 'parent_id' => 6, 'level' => 2, 'job_field_id' => 6],
            ['title' => 'مالی', 'parent_id' => 6, 'level' => 2, 'job_field_id' => 6],
            ['title' => 'کارآفرینی', 'parent_id' => 6, 'level' => 2, 'job_field_id' => 6],
        ];

        // زیرزیرشاخه‌های مدیریت
        $managementSubSubSpecializations = [
            ['title' => 'مدیریت منابع انسانی', 'parent_id' => 33, 'level' => 3, 'job_field_id' => 6],
            ['title' => 'مدیریت مالی', 'parent_id' => 33, 'level' => 3, 'job_field_id' => 6],
        ];

        // زیرزیرشاخه‌های بازاریابی
        $marketingSubSubSpecializations = [
            ['title' => 'بازاریابی دیجیتال', 'parent_id' => 34, 'level' => 3, 'job_field_id' => 6],
            ['title' => 'بازاریابی سنتی', 'parent_id' => 34, 'level' => 3, 'job_field_id' => 6],
        ];

        // زیرزیرشاخه‌های مالی
        $financeSubSubSpecializations = [
            ['title' => 'تحلیل مالی', 'parent_id' => 35, 'level' => 3, 'job_field_id' => 6],
            ['title' => 'مدیریت ریسک', 'parent_id' => 35, 'level' => 3, 'job_field_id' => 6],
        ];

        // زیرزیرشاخه‌های کارآفرینی
        $entrepreneurshipSubSubSpecializations = [
            ['title' => 'استارتاپ‌ها', 'parent_id' => 36, 'level' => 3, 'job_field_id' => 6],
            ['title' => 'نوآوری', 'parent_id' => 36, 'level' => 3, 'job_field_id' => 6],
        ];

        // زیرشاخه‌های حقوق و قانون
        $lawSubSpecializations = [
            ['title' => 'حقوق مدنی', 'parent_id' => 7, 'level' => 2, 'job_field_id' => 7],
            ['title' => 'حقوق جزا', 'parent_id' => 7, 'level' => 2, 'job_field_id' => 7],
            ['title' => 'حقوق تجاری', 'parent_id' => 7, 'level' => 2, 'job_field_id' => 7],
        ];

        // زیرزیرشاخه‌های حقوق مدنی
        $civilLawSubSubSpecializations = [
            ['title' => 'قراردادها', 'parent_id' => 37, 'level' => 3, 'job_field_id' => 7],
            ['title' => 'حقوق خانواده', 'parent_id' => 37, 'level' => 3, 'job_field_id' => 7],
        ];

        // زیرزیرشاخه‌های حقوق جزا
        $criminalLawSubSubSpecializations = [
            ['title' => 'حقوق کیفری', 'parent_id' => 38, 'level' => 3, 'job_field_id' => 7],
            ['title' => 'حقوق اداری', 'parent_id' => 38, 'level' => 3, 'job_field_id' => 7],
        ];

        // زیرزیرشاخه‌های حقوق تجاری
        $commercialLawSubSubSpecializations = [
            ['title' => 'حقوق شرکت‌ها', 'parent_id' => 39, 'level' => 3, 'job_field_id' => 7],
            ['title' => 'حقوق بانکی', 'parent_id' => 39, 'level' => 3, 'job_field_id' => 7],
        ];

        // زیرشاخه‌های علوم کامپیوتر و فناوری اطلاعات
        $computerScienceSubSpecializations = [
            ['title' => 'برنامه‌نویسی', 'parent_id' => 8, 'level' => 2, 'job_field_id' => 8],
            ['title' => 'شبکه و امنیت', 'parent_id' => 8, 'level' => 2, 'job_field_id' => 8],
            ['title' => 'داده‌کاوی و هوش مصنوعی', 'parent_id' => 8, 'level' => 2, 'job_field_id' => 8],
            ['title' => 'انفورماتیک', 'parent_id' => 8, 'level' => 2, 'job_field_id' => 8],
        ];

        // زیرزیرشاخه‌های برنامه‌نویسی
        $programmingSubSubSpecializations = [
            ['title' => 'برنامه‌نویسی وب', 'parent_id' => 40, 'level' => 3, 'job_field_id' => 8],
            ['title' => 'برنامه‌نویسی موبایل', 'parent_id' => 40, 'level' => 3, 'job_field_id' => 8],
        ];

        // زیرزیرشاخه‌های شبکه و امنیت
        $networkSecuritySubSubSpecializations = [
            ['title' => 'امنیت سایبری', 'parent_id' => 41, 'level' => 3, 'job_field_id' => 8],
            ['title' => 'مدیریت شبکه', 'parent_id' => 41, 'level' => 3, 'job_field_id' => 8],
        ];

        // زیرزیرشاخه‌های داده‌کاوی و هوش مصنوعی
        $dataScienceSubSubSpecializations = [
            ['title' => 'یادگیری ماشین', 'parent_id' => 42, 'level' => 3, 'job_field_id' => 8],
            ['title' => 'داده‌کاوی', 'parent_id' => 42, 'level' => 3, 'job_field_id' => 8],
        ];

        // زیرزیرشاخه‌های انفورماتیک
        $informaticsSubSubSpecializations = [
            ['title' => 'مدیریت داده‌ها', 'parent_id' => 43, 'level' => 3, 'job_field_id' => 8],
            ['title' => 'سیستم‌های اطلاعاتی', 'parent_id' => 43, 'level' => 3, 'job_field_id' => 8],
        ];

        // زیرشاخه‌های هنر و طراحی
        $artDesignSubSpecializations = [
            ['title' => 'طراحی گرافیک', 'parent_id' => 9, 'level' => 2, 'job_field_id' => 9],
            ['title' => 'هنرهای تجسمی', 'parent_id' => 9, 'level' => 2, 'job_field_id' => 9],
            ['title' => 'عکاسی', 'parent_id' => 9, 'level' => 2, 'job_field_id' => 9],
        ];

        // زیرزیرشاخه‌های طراحی گرافیک
        $graphicDesignSubSubSpecializations = [
            ['title' => 'طراحی چاپ', 'parent_id' => 44, 'level' => 3, 'job_field_id' => 9],
            ['title' => 'طراحی دیجیتال', 'parent_id' => 44, 'level' => 3, 'job_field_id' => 9],
        ];

        // زیرزیرشاخه‌های هنرهای تجسمی
        $visualArtsSubSubSpecializations = [
            ['title' => 'نقاشی', 'parent_id' => 45, 'level' => 3, 'job_field_id' => 9],
            ['title' => 'مجسمه‌سازی', 'parent_id' => 45, 'level' => 3, 'job_field_id' => 9],
        ];

        // زیرزیرشاخه‌های عکاسی
        $photographySubSubSpecializations = [
            ['title' => 'عکاسی تبلیغاتی', 'parent_id' => 46, 'level' => 3, 'job_field_id' => 9],
            ['title' => 'عکاسی هنری', 'parent_id' => 46, 'level' => 3, 'job_field_id' => 9],
        ];

        // زیرشاخه‌های معماری و شهرسازی
        $architectureSubSpecializations = [
            ['title' => 'معماری', 'parent_id' => 10, 'level' => 2, 'job_field_id' => 10],
            ['title' => 'شهرسازی', 'parent_id' => 10, 'level' => 2, 'job_field_id' => 10],
        ];

        // زیرزیرشاخه‌های معماری
        $architectureSubSubSpecializations = [
            ['title' => 'طراحی داخلی', 'parent_id' => 47, 'level' => 3, 'job_field_id' => 10],
            ['title' => 'معماری خارجی', 'parent_id' => 47, 'level' => 3, 'job_field_id' => 10],
        ];

        // زیرزیرشاخه‌های شهرسازی
        $urbanPlanningSubSubSpecializations = [
            ['title' => 'طراحی شهری', 'parent_id' => 48, 'level' => 3, 'job_field_id' => 10],
            ['title' => 'برنامه‌ریزی شهری', 'parent_id' => 48, 'level' => 3, 'job_field_id' => 10],
        ];

        // زیرشاخه‌های آموزش و پژوهش
        $educationResearchSubSpecializations = [
            ['title' => 'آموزش', 'parent_id' => 11, 'level' => 2, 'job_field_id' => 11],
            ['title' => 'پژوهش', 'parent_id' => 11, 'level' => 2, 'job_field_id' => 11],
        ];

        // زیرزیرشاخه‌های آموزش
        $educationSubSubSpecializations = [
            ['title' => 'آموزش ابتدایی', 'parent_id' => 49, 'level' => 3, 'job_field_id' => 11],
            ['title' => 'آموزش عالی', 'parent_id' => 49, 'level' => 3, 'job_field_id' => 11],
        ];

        // زیرزیرشاخه‌های پژوهش
        $researchSubSubSpecializations = [
            ['title' => 'پژوهش‌های علمی', 'parent_id' => 50, 'level' => 3, 'job_field_id' => 11],
            ['title' => 'پژوهش‌های کاربردی', 'parent_id' => 50, 'level' => 3, 'job_field_id' => 11],
        ];

        // زیرشاخه‌های محیط زیست و پایداری
        $environmentSubSpecializations = [
            ['title' => 'مدیریت منابع طبیعی', 'parent_id' => 12, 'level' => 2, 'job_field_id' => 12],
            ['title' => 'انرژی‌های تجدیدپذیر', 'parent_id' => 12, 'level' => 2, 'job_field_id' => 12],
            ['title' => 'حفظ محیط زیست', 'parent_id' => 12, 'level' => 2, 'job_field_id' => 12],
        ];

        // زیرزیرشاخه‌های مدیریت منابع طبیعی
        $naturalResourcesSubSubSpecializations = [
            ['title' => 'مدیریت جنگل‌ها', 'parent_id' => 51, 'level' => 3, 'job_field_id' => 12],
            ['title' => 'مدیریت آب', 'parent_id' => 51, 'level' => 3, 'job_field_id' => 12],
        ];

        // زیرزیرشاخه‌های انرژی‌های تجدیدپذیر
        $renewableEnergySubSubSpecializations = [
            ['title' => 'انرژی خورشیدی', 'parent_id' => 52, 'level' => 3, 'job_field_id' => 12],
            ['title' => 'انرژی بادی', 'parent_id' => 52, 'level' => 3, 'job_field_id' => 12],
        ];

        // زیرزیرشاخه‌های حفظ محیط زیست
        $environmentalProtectionSubSubSpecializations = [
            ['title' => 'حفاظت از حیات وحش', 'parent_id' => 53, 'level' => 3, 'job_field_id' => 12],
            ['title' => 'مدیریت پسماند', 'parent_id' => 53, 'level' => 3, 'job_field_id' => 12],
        ];

        // ایجاد تمام زیرشاخه‌ها و زیرزیرشاخه‌ها
        $allSubSpecializations = array_merge(
            $engineeringSubSpecializations,
            $electricalEngineeringSubSpecializations,
            $mechanicalEngineeringSubSpecializations,
            $civilEngineeringSubSpecializations,
            $chemicalEngineeringSubSpecializations,
            $industrialEngineeringSubSpecializations,
            $medicalSubSpecializations,
            $generalMedicineSubSpecializations,
            $surgerySubSpecializations,
            $dentistrySubSpecializations,
            $pharmacySubSpecializations,
            $nursingSubSpecializations,
            $humanitiesSubSpecializations,
            $psychologySubSpecializations,
            $philosophySubSpecializations,
            $literatureSubSpecializations,
            $basicSciencesSubSpecializations,
            $physicsSubSpecializations,
            $chemistrySubSpecializations,
            $mathematicsSubSpecializations,
            $biologySubSpecializations,
            $socialSciencesSubSpecializations,
            $sociologySubSpecializations,
            $economicsSubSpecializations,
            $politicalScienceSubSpecializations,
            $managementSubSpecializations,
            $managementSubSubSpecializations,
            $marketingSubSubSpecializations,
            $financeSubSubSpecializations,
            $entrepreneurshipSubSubSpecializations,
            $lawSubSpecializations,
            $civilLawSubSubSpecializations,
            $criminalLawSubSubSpecializations,
            $commercialLawSubSubSpecializations,
            $computerScienceSubSpecializations,
            $programmingSubSubSpecializations,
            $networkSecuritySubSubSpecializations,
            $dataScienceSubSubSpecializations,
            $informaticsSubSubSpecializations,
            $artDesignSubSpecializations,
            $graphicDesignSubSubSpecializations,
            $visualArtsSubSubSpecializations,
            $photographySubSubSpecializations,
            $architectureSubSpecializations,
            $architectureSubSubSpecializations,
            $urbanPlanningSubSubSpecializations,
            $educationResearchSubSpecializations,
            $educationSubSubSpecializations,
            $researchSubSubSpecializations,
            $environmentSubSpecializations,
            $naturalResourcesSubSubSpecializations,
            $renewableEnergySubSubSpecializations,
            $environmentalProtectionSubSubSpecializations
        );

        foreach ($allSubSpecializations as $subSpecialization) {
            Specialization::updateOrCreate(
                ['title' => $subSpecialization['title']],
                $subSpecialization
            );
        }
    }
}