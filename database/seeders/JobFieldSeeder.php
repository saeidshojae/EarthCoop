<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JobField;
use Illuminate\Support\Facades\DB;

class JobFieldSeeder extends Seeder
{
    public function run()
    {
        DB::beginTransaction();

        try {
            // ایجاد صنعت و زیرمجموعه‌های آن
            $industry = JobField::firstOrCreate(['title' => 'صنعت'], ['parent_id' => null, 'level' => 1]);

            // صنعت غذایی
            $foodIndustry = JobField::firstOrCreate(['title' => 'صنعت غذایی'], ['parent_id' => $industry->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'نانوایی'], ['parent_id' => $foodIndustry->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'لبنیات'], ['parent_id' => $foodIndustry->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'کنسرو و فرآوری مواد غذایی'], ['parent_id' => $foodIndustry->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'تولید نوشیدنی'], ['parent_id' => $foodIndustry->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'تولید تنقلات'], ['parent_id' => $foodIndustry->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'تولید غذاهای آماده'], ['parent_id' => $foodIndustry->id, 'level' => 3]);

            // صنعت نساجی
            $textileIndustry = JobField::firstOrCreate(['title' => 'صنعت نساجی'], ['parent_id' => $industry->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'تولید پارچه'], ['parent_id' => $textileIndustry->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'پوشاک'], ['parent_id' => $textileIndustry->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'کفش'], ['parent_id' => $textileIndustry->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'تولید لوازم خانگی نساجی (روتختی، پرده و ...)'], ['parent_id' => $textileIndustry->id, 'level' => 3]);

            // صنعت خودروسازی
            $automotiveIndustry = JobField::firstOrCreate(['title' => 'صنعت خودروسازی'], ['parent_id' => $industry->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'تولید خودرو'], ['parent_id' => $automotiveIndustry->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'قطعات خودرو'], ['parent_id' => $automotiveIndustry->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'خدمات پس از فروش خودرو'], ['parent_id' => $automotiveIndustry->id, 'level' => 3]);

            // صنعت الکترونیک
            $electronicsIndustry = JobField::firstOrCreate(['title' => 'صنعت الکترونیک'], ['parent_id' => $industry->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'تولید لوازم خانگی'], ['parent_id' => $electronicsIndustry->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'تولید تجهیزات الکترونیکی'], ['parent_id' => $electronicsIndustry->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'تولید گوشی‌های هوشمند'], ['parent_id' => $electronicsIndustry->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'تولید تجهیزات مخابراتی'], ['parent_id' => $electronicsIndustry->id, 'level' => 3]);

            // صنعت شیمیایی
            $chemicalIndustry = JobField::firstOrCreate(['title' => 'صنعت شیمیایی'], ['parent_id' => $industry->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'تولید مواد شیمیایی'], ['parent_id' => $chemicalIndustry->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'داروسازی'], ['parent_id' => $chemicalIndustry->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'تولید کود و سموم'], ['parent_id' => $chemicalIndustry->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'تولید پلاستیک و لاستیک'], ['parent_id' => $chemicalIndustry->id, 'level' => 3]);

            // صنعت فلزات
            $metalsIndustry = JobField::firstOrCreate(['title' => 'صنعت فلزات'], ['parent_id' => $industry->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'تولید فولاد'], ['parent_id' => $metalsIndustry->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'تولید آلومینیوم'], ['parent_id' => $metalsIndustry->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'تولید مس'], ['parent_id' => $metalsIndustry->id, 'level' => 3]);

            // صنعت ساختمان
            $constructionIndustry = JobField::firstOrCreate(['title' => 'صنعت ساختمان'], ['parent_id' => $industry->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'تولید مصالح ساختمانی (سیمان، آجر، کاشی و ...)'], ['parent_id' => $constructionIndustry->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'تولید تجهیزات ساختمانی (لوله، اتصالات و ...)'], ['parent_id' => $constructionIndustry->id, 'level' => 3]);

            // ایجاد خدمات و زیرمجموعه‌های آن
            $services = JobField::firstOrCreate(['title' => 'خدمات'], ['parent_id' => null, 'level' => 1]);

            // خدمات مالی
            $financialServices = JobField::firstOrCreate(['title' => 'خدمات مالی'], ['parent_id' => $services->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'بانکداری'], ['parent_id' => $financialServices->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'بیمه'], ['parent_id' => $financialServices->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'مشاوره مالی'], ['parent_id' => $financialServices->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'حسابداری و خدمات مالیاتی'], ['parent_id' => $financialServices->id, 'level' => 3]);

            // خدمات بهداشتی
            $healthServices = JobField::firstOrCreate(['title' => 'خدمات بهداشتی'], ['parent_id' => $services->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'بیمارستان‌ها'], ['parent_id' => $healthServices->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'کلینیک‌ها'], ['parent_id' => $healthServices->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'داروخانه‌ها'], ['parent_id' => $healthServices->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'خدمات درمانی خانگی'], ['parent_id' => $healthServices->id, 'level' => 3]);

            // خدمات آموزشی
            $educationServices = JobField::firstOrCreate(['title' => 'خدمات آموزشی'], ['parent_id' => $services->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'مدارس'], ['parent_id' => $educationServices->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'دانشگاه‌ها'], ['parent_id' => $educationServices->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'آموزشگاه‌های آزاد'], ['parent_id' => $educationServices->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'دوره‌های آنلاین'], ['parent_id' => $educationServices->id, 'level' => 3]);

            // خدمات گردشگری
            $tourismServices = JobField::firstOrCreate(['title' => 'خدمات گردشگری'], ['parent_id' => $services->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'هتل‌ها'], ['parent_id' => $tourismServices->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'آژانس‌های مسافرتی'], ['parent_id' => $tourismServices->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'راهنمایان گردشگری'], ['parent_id' => $tourismServices->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'خدمات تفریحی و سرگرمی'], ['parent_id' => $tourismServices->id, 'level' => 3]);

            // خدمات مشاوره‌ای
            $consultingServices = JobField::firstOrCreate(['title' => 'خدمات مشاوره‌ای'], ['parent_id' => $services->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'مشاوره مدیریت'], ['parent_id' => $consultingServices->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'مشاوره حقوقی'], ['parent_id' => $consultingServices->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'مشاوره منابع انسانی'], ['parent_id' => $consultingServices->id, 'level' => 3]);

            // خدمات فناوری اطلاعات
            $itServices = JobField::firstOrCreate(['title' => 'خدمات فناوری اطلاعات'], ['parent_id' => $services->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'پشتیبانی IT'], ['parent_id' => $itServices->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'خدمات شبکه'], ['parent_id' => $itServices->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'خدمات ابری'], ['parent_id' => $itServices->id, 'level' => 3]);

            // ایجاد کشاورزی و زیرمجموعه‌های آن
            $agriculture = JobField::firstOrCreate(['title' => 'کشاورزی'], ['parent_id' => null, 'level' => 1]);

            // کشاورزی زراعی
            $cropAgriculture = JobField::firstOrCreate(['title' => 'کشاورزی زراعی'], ['parent_id' => $agriculture->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'تولید غلات (گندم، برنج، جو و ...)'], ['parent_id' => $cropAgriculture->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'میوه و سبزیجات'], ['parent_id' => $cropAgriculture->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'تولید گیاهان دارویی'], ['parent_id' => $cropAgriculture->id, 'level' => 3]);

            // دامداری
            $livestock = JobField::firstOrCreate(['title' => 'دامداری'], ['parent_id' => $agriculture->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'پرورش دام (گوسفند، گاو، مرغ و ...)'], ['parent_id' => $livestock->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'تولید لبنیات'], ['parent_id' => $livestock->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'تولید گوشت'], ['parent_id' => $livestock->id, 'level' => 3]);

            // شیلات
            $fisheries = JobField::firstOrCreate(['title' => 'شیلات'], ['parent_id' => $agriculture->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'پرورش ماهی'], ['parent_id' => $fisheries->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'صید و فرآوری آبزیان'], ['parent_id' => $fisheries->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'تولید میگو'], ['parent_id' => $fisheries->id, 'level' => 3]);

            // کشاورزی ارگانیک
            $organicAgriculture = JobField::firstOrCreate(['title' => 'کشاورزی ارگانیک'], ['parent_id' => $agriculture->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'تولید محصولات ارگانیک'], ['parent_id' => $organicAgriculture->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'کشاورزی پایدار'], ['parent_id' => $organicAgriculture->id, 'level' => 3]);

            // ایجاد ساخت و ساز و زیرمجموعه‌های آن
            $construction = JobField::firstOrCreate(['title' => 'ساخت و ساز'], ['parent_id' => null, 'level' => 1]);

            // ساخت و ساز مسکونی
            $residentialConstruction = JobField::firstOrCreate(['title' => 'ساخت و ساز مسکونی'], ['parent_id' => $construction->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'ساختمان‌های چند واحدی'], ['parent_id' => $residentialConstruction->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'ویلاها و خانه‌های تک‌واحدی'], ['parent_id' => $residentialConstruction->id, 'level' => 3]);

            // ساخت و ساز تجاری
            $commercialConstruction = JobField::firstOrCreate(['title' => 'ساخت و ساز تجاری'], ['parent_id' => $construction->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'مراکز خرید'], ['parent_id' => $commercialConstruction->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'ادارات و دفاتر'], ['parent_id' => $commercialConstruction->id, 'level' => 3]);

            // ساخت و ساز زیرساختی
            $infrastructureConstruction = JobField::firstOrCreate(['title' => 'ساخت و ساز زیرساختی'], ['parent_id' => $construction->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'جاده‌ها'], ['parent_id' => $infrastructureConstruction->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'پل‌ها'], ['parent_id' => $infrastructureConstruction->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'تونل‌ها'], ['parent_id' => $infrastructureConstruction->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'فرودگاه‌ها'], ['parent_id' => $infrastructureConstruction->id, 'level' => 3]);

            // ساخت و ساز صنعتی
            $industrialConstruction = JobField::firstOrCreate(['title' => 'ساخت و ساز صنعتی'], ['parent_id' => $construction->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'کارخانه‌ها'], ['parent_id' => $industrialConstruction->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'انبارها'], ['parent_id' => $industrialConstruction->id, 'level' => 3]);

            // ایجاد تجارت و زیرمجموعه‌های آن
            $commerce = JobField::firstOrCreate(['title' => 'تجارت'], ['parent_id' => null, 'level' => 1]);

            // تجارت عمده
            $wholesaleCommerce = JobField::firstOrCreate(['title' => 'تجارت عمده'], ['parent_id' => $commerce->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'عمده‌فروشی کالاها'], ['parent_id' => $wholesaleCommerce->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'توزیع محصولات'], ['parent_id' => $wholesaleCommerce->id, 'level' => 3]);

            // تجارت خرده
            $retailCommerce = JobField::firstOrCreate(['title' => 'تجارت خرده'], ['parent_id' => $commerce->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'فروشگاه‌های زنجیره‌ای'], ['parent_id' => $retailCommerce->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'فروشگاه‌های محلی'], ['parent_id' => $retailCommerce->id, 'level' => 3]);

            // تجارت الکترونیک
            $eCommerce = JobField::firstOrCreate(['title' => 'تجارت الکترونیک'], ['parent_id' => $commerce->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'فروشگاه‌های آنلاین'], ['parent_id' => $eCommerce->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'پلتفرم‌های خرید و فروش'], ['parent_id' => $eCommerce->id, 'level' => 3]);

            // صادرات و واردات
            $importExport = JobField::firstOrCreate(['title' => 'صادرات و واردات'], ['parent_id' => $commerce->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'صادرات کالا'], ['parent_id' => $importExport->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'واردات کالا'], ['parent_id' => $importExport->id, 'level' => 3]);

            // ایجاد فناوری اطلاعات و ارتباطات و زیرمجموعه‌های آن
            $ict = JobField::firstOrCreate(['title' => 'فناوری اطلاعات و ارتباطات'], ['parent_id' => null, 'level' => 1]);

            // برنامه‌نویسی
            $programming = JobField::firstOrCreate(['title' => 'برنامه‌نویسی'], ['parent_id' => $ict->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'توسعه نرم‌افزار'], ['parent_id' => $programming->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'توسعه وب'], ['parent_id' => $programming->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'توسعه اپلیکیشن‌های موبایل'], ['parent_id' => $programming->id, 'level' => 3]);

            // تحلیل داده
            $dataAnalysis = JobField::firstOrCreate(['title' => 'تحلیل داده'], ['parent_id' => $ict->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'داده‌کاوی'], ['parent_id' => $dataAnalysis->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'تحلیل داده‌های کلان'], ['parent_id' => $dataAnalysis->id, 'level' => 3]);

            // امنیت سایبری
            $cybersecurity = JobField::firstOrCreate(['title' => 'امنیت سایبری'], ['parent_id' => $ict->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'مشاوره امنیتی'], ['parent_id' => $cybersecurity->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'خدمات امنیتی'], ['parent_id' => $cybersecurity->id, 'level' => 3]);

            // مدیریت سیستم‌های اطلاعاتی
            $informationSystems = JobField::firstOrCreate(['title' => 'مدیریت سیستم‌های اطلاعاتی'], ['parent_id' => $ict->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'مدیریت پایگاه داده'], ['parent_id' => $informationSystems->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'مدیریت شبکه'], ['parent_id' => $informationSystems->id, 'level' => 3]);

            // فناوری‌های نوین
            $emergingTechnologies = JobField::firstOrCreate(['title' => 'فناوری‌های نوین'], ['parent_id' => $ict->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'هوش مصنوعی و یادگیری ماشین'], ['parent_id' => $emergingTechnologies->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'اینترنت اشیاء (IoT)'], ['parent_id' => $emergingTechnologies->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'بلاک‌چین'], ['parent_id' => $emergingTechnologies->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'واقعیت مجازی و واقعیت افزوده'], ['parent_id' => $emergingTechnologies->id, 'level' => 3]);

            // ایجاد هنر و فرهنگ و زیرمجموعه‌های آن
            $artsCulture = JobField::firstOrCreate(['title' => 'هنر و فرهنگ'], ['parent_id' => null, 'level' => 1]);

            // هنرهای تجسمی
            $visualArts = JobField::firstOrCreate(['title' => 'هنرهای تجسمی'], ['parent_id' => $artsCulture->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'نقاشی'], ['parent_id' => $visualArts->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'مجسمه‌سازی'], ['parent_id' => $visualArts->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'عکاسی'], ['parent_id' => $visualArts->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'طراحی گرافیک'], ['parent_id' => $visualArts->id, 'level' => 3]);

            // موسیقی
            $music = JobField::firstOrCreate(['title' => 'موسیقی'], ['parent_id' => $artsCulture->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'نوازندگی'], ['parent_id' => $music->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'آهنگسازی'], ['parent_id' => $music->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'تولید موسیقی'], ['parent_id' => $music->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'آموزش موسیقی'], ['parent_id' => $music->id, 'level' => 3]);

            // تئاتر
            $theater = JobField::firstOrCreate(['title' => 'تئاتر'], ['parent_id' => $artsCulture->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'بازیگری'], ['parent_id' => $theater->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'کارگردانی'], ['parent_id' => $theater->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'نویسندگی نمایشنامه'], ['parent_id' => $theater->id, 'level' => 3]);

            // ادبیات
            $literature = JobField::firstOrCreate(['title' => 'ادبیات'], ['parent_id' => $artsCulture->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'نویسندگی'], ['parent_id' => $literature->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'شعر'], ['parent_id' => $literature->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'نقد ادبی'], ['parent_id' => $literature->id, 'level' => 3]);

            // سینما و تلویزیون
            $cinemaTv = JobField::firstOrCreate(['title' => 'سینما و تلویزیون'], ['parent_id' => $artsCulture->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'کارگردانی'], ['parent_id' => $cinemaTv->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'فیلم‌نامه‌نویسی'], ['parent_id' => $cinemaTv->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'تولید و تدوین فیلم'], ['parent_id' => $cinemaTv->id, 'level' => 3]);

            // صنایع دستی
            $handicrafts = JobField::firstOrCreate(['title' => 'صنایع دستی'], ['parent_id' => $artsCulture->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'سفالگری'], ['parent_id' => $handicrafts->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'بافندگی'], ['parent_id' => $handicrafts->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'صنایع چوبی'], ['parent_id' => $handicrafts->id, 'level' => 3]);

            // ایجاد مشاوره و خدمات حرفه‌ای و زیرمجموعه‌های آن
            $professionalServices = JobField::firstOrCreate(['title' => 'مشاوره و خدمات حرفه‌ای'], ['parent_id' => null, 'level' => 1]);

            // مشاوره مدیریت
            $managementConsulting = JobField::firstOrCreate(['title' => 'مشاوره مدیریت'], ['parent_id' => $professionalServices->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'مشاوره استراتژیک'], ['parent_id' => $managementConsulting->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'مشاوره بهبود فرآیند'], ['parent_id' => $managementConsulting->id, 'level' => 3]);

            // مشاوره حقوقی
            $legalConsulting = JobField::firstOrCreate(['title' => 'مشاوره حقوقی'], ['parent_id' => $professionalServices->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'مشاوره حقوقی عمومی'], ['parent_id' => $legalConsulting->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'مشاوره حقوقی تجاری'], ['parent_id' => $legalConsulting->id, 'level' => 3]);

            // مشاوره منابع انسانی
            $hrConsulting = JobField::firstOrCreate(['title' => 'مشاوره منابع انسانی'], ['parent_id' => $professionalServices->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'استخدام و گزینش'], ['parent_id' => $hrConsulting->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'آموزش و توسعه منابع انسانی'], ['parent_id' => $hrConsulting->id, 'level' => 3]);

            // مشاوره بازاریابی
            $marketingConsulting = JobField::firstOrCreate(['title' => 'مشاوره بازاریابی'], ['parent_id' => $professionalServices->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'مشاوره برندینگ'], ['parent_id' => $marketingConsulting->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'مشاوره تبلیغات'], ['parent_id' => $marketingConsulting->id, 'level' => 3]);

            // ایجاد خدمات عمومی و دولتی و زیرمجموعه‌های آن
            $publicServices = JobField::firstOrCreate(['title' => 'خدمات عمومی و دولتی'], ['parent_id' => null, 'level' => 1]);

            // خدمات دولتی
            $governmentServices = JobField::firstOrCreate(['title' => 'خدمات دولتی'], ['parent_id' => $publicServices->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'خدمات اجتماعی'], ['parent_id' => $governmentServices->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'خدمات عمومی'], ['parent_id' => $governmentServices->id, 'level' => 3]);

            // خدمات شهری
            $urbanServices = JobField::firstOrCreate(['title' => 'خدمات شهری'], ['parent_id' => $publicServices->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'مدیریت شهری'], ['parent_id' => $urbanServices->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'خدمات حمل و نقل عمومی'], ['parent_id' => $urbanServices->id, 'level' => 3]);

            // خدمات محیط زیست
            $environmentalServices = JobField::firstOrCreate(['title' => 'خدمات محیط زیست'], ['parent_id' => $publicServices->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'مدیریت پسماند'], ['parent_id' => $environmentalServices->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'حفاظت از محیط زیست'], ['parent_id' => $environmentalServices->id, 'level' => 3]);

            // ایجاد ورزش و تفریح و زیرمجموعه‌های آن
            $sportsRecreation = JobField::firstOrCreate(['title' => 'ورزش و تفریح'], ['parent_id' => null, 'level' => 1]);

            // ورزش‌های حرفه‌ای
            $professionalSports = JobField::firstOrCreate(['title' => 'ورزش‌های حرفه‌ای'], ['parent_id' => $sportsRecreation->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'مربیگری'], ['parent_id' => $professionalSports->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'مدیریت تیم‌های ورزشی'], ['parent_id' => $professionalSports->id, 'level' => 3]);

            // ورزش‌های آماتوری
            $amateurSports = JobField::firstOrCreate(['title' => 'ورزش‌های آماتوری'], ['parent_id' => $sportsRecreation->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'آموزش ورزش‌های مختلف'], ['parent_id' => $amateurSports->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'برگزاری رویدادهای ورزشی'], ['parent_id' => $amateurSports->id, 'level' => 3]);

            // تفریحات و سرگرمی
            $recreation = JobField::firstOrCreate(['title' => 'تفریحات و سرگرمی'], ['parent_id' => $sportsRecreation->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'پارک‌های تفریحی'], ['parent_id' => $recreation->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'سینماها و تئاترها'], ['parent_id' => $recreation->id, 'level' => 3]);

            // ایجاد رسانه و ارتباطات و زیرمجموعه‌های آن
            $mediaCommunications = JobField::firstOrCreate(['title' => 'رسانه و ارتباطات'], ['parent_id' => null, 'level' => 1]);

            // رسانه‌های چاپی
            $printMedia = JobField::firstOrCreate(['title' => 'رسانه‌های چاپی'], ['parent_id' => $mediaCommunications->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'روزنامه‌ها'], ['parent_id' => $printMedia->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'مجلات'], ['parent_id' => $printMedia->id, 'level' => 3]);

            // رسانه‌های دیجیتال
            $digitalMedia = JobField::firstOrCreate(['title' => 'رسانه‌های دیجیتال'], ['parent_id' => $mediaCommunications->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'وب‌سایت‌ها'], ['parent_id' => $digitalMedia->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'پادکست‌ها'], ['parent_id' => $digitalMedia->id, 'level' => 3]);

            // رسانه‌های اجتماعی
            $socialMedia = JobField::firstOrCreate(['title' => 'رسانه‌های اجتماعی'], ['parent_id' => $mediaCommunications->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'مدیریت شبکه‌های اجتماعی'], ['parent_id' => $socialMedia->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'تولید محتوا'], ['parent_id' => $socialMedia->id, 'level' => 3]);

            // ایجاد خدمات حمل و نقل و لجستیک و زیرمجموعه‌های آن
            $transportLogistics = JobField::firstOrCreate(['title' => 'خدمات حمل و نقل و لجستیک'], ['parent_id' => null, 'level' => 1]);

            // حمل و نقل جاده‌ای
            $roadTransport = JobField::firstOrCreate(['title' => 'حمل و نقل جاده‌ای'], ['parent_id' => $transportLogistics->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'حمل و نقل بار'], ['parent_id' => $roadTransport->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'حمل و نقل مسافر'], ['parent_id' => $roadTransport->id, 'level' => 3]);

            // حمل و نقل هوایی
            $airTransport = JobField::firstOrCreate(['title' => 'حمل و نقل هوایی'], ['parent_id' => $transportLogistics->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'خدمات فرودگاهی'], ['parent_id' => $airTransport->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'حمل و نقل بار هوایی'], ['parent_id' => $airTransport->id, 'level' => 3]);

            // حمل و نقل دریایی
            $maritimeTransport = JobField::firstOrCreate(['title' => 'حمل و نقل دریایی'], ['parent_id' => $transportLogistics->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'حمل و نقل بار دریایی'], ['parent_id' => $maritimeTransport->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'خدمات بندری'], ['parent_id' => $maritimeTransport->id, 'level' => 3]);

            // لجستیک و زنجیره تأمین
            $logisticsSupplyChain = JobField::firstOrCreate(['title' => 'لجستیک و زنجیره تأمین'], ['parent_id' => $transportLogistics->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'مدیریت انبار'], ['parent_id' => $logisticsSupplyChain->id, 'level' => 3]);
            JobField::firstOrCreate(['title' => 'توزیع و حمل و نقل'], ['parent_id' => $logisticsSupplyChain->id, 'level' => 3]);

            // ایجاد املاک و مستغلات و زیرمجموعه‌های آن
            $realEstate = JobField::firstOrCreate(['title' => 'املاک و مستغلات'], ['parent_id' => null, 'level' => 1]);

            JobField::firstOrCreate(['title' => 'خرید و فروش املاک'], ['parent_id' => $realEstate->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'اجاره و رهن املاک'], ['parent_id' => $realEstate->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'مدیریت املاک'], ['parent_id' => $realEstate->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'مشاوران املاک'], ['parent_id' => $realEstate->id, 'level' => 2]);

            // ایجاد محیط زیست و پایداری و زیرمجموعه‌های آن
            $environmentSustainability = JobField::firstOrCreate(['title' => 'محیط زیست و پایداری'], ['parent_id' => null, 'level' => 1]);

            JobField::firstOrCreate(['title' => 'مدیریت منابع طبیعی'], ['parent_id' => $environmentSustainability->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'انرژی‌های تجدیدپذیر'], ['parent_id' => $environmentSustainability->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'مدیریت پسماند'], ['parent_id' => $environmentSustainability->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'حفاظت از محیط زیست'], ['parent_id' => $environmentSustainability->id, 'level' => 2]);

            // ایجاد آموزش و پژوهش و زیرمجموعه‌های آن
            $educationResearch = JobField::firstOrCreate(['title' => 'آموزش و پژوهش'], ['parent_id' => null, 'level' => 1]);

            JobField::firstOrCreate(['title' => 'مدارس و مراکز آموزشی'], ['parent_id' => $educationResearch->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'دانشگاه‌ها و مؤسسات آموزش عالی'], ['parent_id' => $educationResearch->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'پژوهش و تحقیق'], ['parent_id' => $educationResearch->id, 'level' => 2]);

            // ایجاد قانون و مقررات و زیرمجموعه‌های آن
            $lawRegulations = JobField::firstOrCreate(['title' => 'قانون و مقررات'], ['parent_id' => null, 'level' => 1]);

            JobField::firstOrCreate(['title' => 'وکالت و مشاوره حقوقی'], ['parent_id' => $lawRegulations->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'قضاوت و دادستانی'], ['parent_id' => $lawRegulations->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'مشاوره مالیاتی'], ['parent_id' => $lawRegulations->id, 'level' => 2]);

            // ایجاد بهداشت و سلامت و زیرمجموعه‌های آن
            $healthWellness = JobField::firstOrCreate(['title' => 'بهداشت و سلامت'], ['parent_id' => null, 'level' => 1]);

            JobField::firstOrCreate(['title' => 'پزشکی و درمان'], ['parent_id' => $healthWellness->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'داروسازی'], ['parent_id' => $healthWellness->id, 'level' => 2]);
            JobField::firstOrCreate(['title' => 'مراقبت‌های خانگی'], ['parent_id' => $healthWellness->id, 'level' => 2]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}