<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        if (!Schema::hasTable('pages')) {
            return;
        }

        // Content in Farsi
        $farsiContent = '<p>&nbsp;</p>

<h2>سهامدار عزیز، به EarthCoop خوش آمدید!</h2>

<p>&nbsp;</p>

<p><strong>EarthCoop</strong> یک پلتفرم جهانی برای همکاری اجتماعی و اقتصادی است که با هدف تحقق <em>عدالت، شفافیت، مشارکت</em> و <em>توسعه پایدار</em> طراحی شده است.</p>

<p>این راهنما به شما کمک می‌کند تا با نحوه‌ی ثبت‌نام، ورود، ساختار گروه‌ها و نظام دموکراتیک EarthCoop آشنا شوید.</p>

<h3>مراحل شروع:</h3>

<ol>
    <li><strong>ثبت‌نام:</strong> بر روی دکمه "ثبت‌نام" کلیک کنید و اطلاعات خود را وارد کنید</li>
    <li><strong>تایید هویت:</strong> ایمیل خود را تایید کنید</li>
    <li><strong>انتخاب گروه:</strong> به گروه‌های مختلف بر اساس علایق و تخصص خود بپیوندید</li>
    <li><strong>شرکت در فعالیت‌ها:</strong> در مشاورات، رای‌گیری‌ها و پروژه‌ها شرکت‌حردار باشید</li>
</ol>

<h3>ویژگی‌های اصلی:</h3>

<ul>
    <li><strong>گروه‌های تخصصی:</strong> برای هر حوزه علمی عملاً یک گروه وجود دارد</li>
    <li><strong>نظام دموکراتیک:</strong> تمام تصمیمات از طریق رای‌گیری‌های منصفانه اتخاذ می‌شود</li>
    <li><strong>سازمان بازار:</strong> محل خرید و فروش محصولات و خدمات</li>
    <li><strong>صرافی‌های دیجیتال:</strong> تبدیل آسان پول دولتی به ارز دیجیتال</li>
</ul>

<h3>نیاز به کمک?</h3>

<p>برای پاسخ به سؤالات خود، به <a href="/contact">صفحه تماس‌ما</a> مراجعه کنید یا از بخش <a href="/help">راهنمایی</a> استفاده کنید.</p>';

        // Content in English
        $englishContent = '<p>&nbsp;</p>

<h2>Dear Shareholder, Welcome to EarthCoop!</h2>

<p>&nbsp;</p>

<p><strong>EarthCoop</strong> is a global platform for social and economic cooperation designed to achieve <em>justice, transparency, participation</em>, and <em>sustainable development</em>.</p>

<p>This guide helps you become familiar with how to register, log in, understand group structure, and EarthCoop\'s democratic system.</p>

<h3>Getting Started Steps:</h3>

<ol>
    <li><strong>Registration:</strong> Click the "Register" button and enter your information</li>
    <li><strong>Identity Verification:</strong> Verify your email address</li>
    <li><strong>Join Groups:</strong> Join different groups based on your interests and expertise</li>
    <li><strong>Participate in Activities:</strong> Take part in consultations, voting, and projects</li>
</ol>

<h3>Key Features:</h3>

<ul>
    <li><strong>Specialized Groups:</strong> There is a dedicated group for each scientific field</li>
    <li><strong>Democratic System:</strong> All decisions are made through fair voting</li>
    <li><strong>Market Organization:</strong> A place to buy and sell products and services</li>
    <li><strong>Digital Exchanges:</strong> Easy conversion of national currency to digital currency</li>
</ul>

<h3>Need Help?</h3>

<p>For answers to your questions, visit our <a href="/contact">contact page</a> or use the <a href="/help">help section</a>.</p>';

        // Update the usage guide page with proper content
        $updateData = [
            'title' => 'Usage Guide',
            'content' => $englishContent,
        ];

        if (Schema::hasColumn('pages', 'content_translations')) {
            $updateData['content_translations'] = json_encode([
                'fa' => $farsiContent,
            ]);
        }
        if (Schema::hasColumn('pages', 'meta_title')) {
            $updateData['meta_title'] = 'Usage Guide - EarthCoop';
        }
        if (Schema::hasColumn('pages', 'meta_description')) {
            $updateData['meta_description'] = 'Complete guide to get started with EarthCoop platform and learn about its features';
        }
        if (Schema::hasColumn('pages', 'meta_title_translations')) {
            $updateData['meta_title_translations'] = json_encode([
                'fa' => 'راهنمای استفاده - ارتکوپ',
            ]);
        }
        if (Schema::hasColumn('pages', 'meta_description_translations')) {
            $updateData['meta_description_translations'] = json_encode([
                'fa' => 'راهنمای کامل برای شروع استفاده از پلتفرم ارتکوپ و آشنایی با ویژگی‌های آن',
            ]);
        }
        if (Schema::hasColumn('pages', 'is_published')) {
            $updateData['is_published'] = true;
        }

        DB::table('pages')
            ->where('slug', 'rahnmay-astfadh')
            ->update($updateData);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        if (!Schema::hasTable('pages')) {
            return;
        }

        // Revert to placeholder if needed
        $rollbackData = [
            'title' => 'Usage Guide',
            'content' => 'Usage guide content will be here.',
        ];

        if (Schema::hasColumn('pages', 'content_translations')) {
            $rollbackData['content_translations'] = json_encode([
                'fa' => 'راهنمای استفاده محتوا در اینجا قرار خواهد گرفت.',
            ]);
        }

        DB::table('pages')
            ->where('slug', 'rahnmay-astfadh')
            ->update($rollbackData);
    }
};