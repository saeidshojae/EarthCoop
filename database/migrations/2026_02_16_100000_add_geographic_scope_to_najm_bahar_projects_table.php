<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * افزودن فیلدهای جغرافیایی برای تعریف محدوده بازار هدف پروژه
     * برای ارسال نوتیفیکیشن هدفمند به سرمایه‌گذاران بر اساس موقعیت مکانی
     */
    public function up(): void
    {
        Schema::table('najm_bahar_projects', function (Blueprint $table) {
            // سطوح جغرافیایی - تا سطح 10
            $table->unsignedBigInteger('geographic_continent_id')->nullable()->after('category_level3_id');
            $table->unsignedBigInteger('geographic_country_id')->nullable()->after('geographic_continent_id');
            $table->unsignedBigInteger('geographic_province_id')->nullable()->after('geographic_country_id');
            $table->unsignedBigInteger('geographic_county_id')->nullable()->after('geographic_province_id');
            $table->unsignedBigInteger('geographic_section_id')->nullable()->after('geographic_county_id');
            
            // سطح شهر یا دهستان (از جدول cities یا rurals)
            $table->unsignedBigInteger('geographic_city_id')->nullable()->after('geographic_section_id');
            $table->unsignedBigInteger('geographic_rural_id')->nullable()->after('geographic_city_id');
            
            // سطح منطقه
            $table->unsignedBigInteger('geographic_region_id')->nullable()->after('geographic_rural_id');
            
            // سطح محله
            $table->unsignedBigInteger('geographic_neighborhood_id')->nullable()->after('geographic_region_id');
            
            // سطح خیابان و کوچه (اختیاری)
            $table->unsignedBigInteger('geographic_street_id')->nullable()->after('geographic_neighborhood_id');
            $table->unsignedBigInteger('geographic_alley_id')->nullable()->after('geographic_street_id');
            
            // ایندکس برای جستجو سریع
            $table->index(['geographic_continent_id', 'geographic_country_id', 'geographic_province_id']);
            $table->index(['geographic_neighborhood_id']);
            $table->index(['geographic_region_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('najm_bahar_projects', function (Blueprint $table) {
            $table->dropIndex(['geographic_continent_id', 'geographic_country_id', 'geographic_province_id']);
            $table->dropIndex(['geographic_neighborhood_id']);
            $table->dropIndex(['geographic_region_id']);
            
            $table->dropColumn([
                'geographic_continent_id',
                'geographic_country_id',
                'geographic_province_id',
                'geographic_county_id',
                'geographic_section_id',
                'geographic_city_id',
                'geographic_rural_id',
                'geographic_region_id',
                'geographic_neighborhood_id',
                'geographic_street_id',
                'geographic_alley_id',
            ]);
        });
    }
};
