<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $intro = [
            'fa' => '<p>به راهنمای EarthCoop خوش آمدید. در سه بخش زیر، مسیر ثبت‌نام، تکمیل اطلاعات و استفاده از گروه‌ها و امکانات حکمرانی را به‌صورت مرحله‌به‌مرحله دنبال کنید.</p>',
            'en' => '<p>Welcome to the EarthCoop user guide. Use the three sections below to follow registration, complete your information, and learn how to use groups and governance features step by step.</p>',
            'ar' => '<p>مرحباً بكم في دليل استخدام EarthCoop. استخدموا الأقسام الثلاثة أدناه للتعرّف خطوة بخطوة على التسجيل واستكمال المعلومات واستخدام المجموعات وميزات الحوكمة.</p>',
        ];

        $descriptions = [
            'fa' => 'راهنمای مرحله‌به‌مرحله ثبت‌نام، تکمیل اطلاعات، گروه‌ها و مشارکت در حکمرانی EarthCoop.',
            'en' => 'A step-by-step guide to EarthCoop registration, profile setup, groups, and governance participation.',
            'ar' => 'دليل خطوة بخطوة للتسجيل في EarthCoop واستكمال الملف الشخصي والمجموعات والمشاركة في الحوكمة.',
        ];

        DB::table('pages')
            ->where('slug', 'rahnmay-astfadh')
            ->update([
                'content' => $intro['fa'],
                'content_translations' => json_encode($intro, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'meta_description' => 'راهنمای مرحله‌به‌مرحله استفاده از EarthCoop',
                'meta_description_translations' => json_encode($descriptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // This is an editorial content migration. Do not restore the obsolete
        // guide copy: it contains outdated invitation rewards, fixed election
        // thresholds, and a location path that no longer reflects the system.
    }
};
