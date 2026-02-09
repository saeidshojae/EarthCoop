<?php

namespace App\Providers;

use App\Services\NajmHoda\NajmHodaOrchestrator;
use App\Services\NajmHoda\Agents\EngineerAgent;
use App\Services\NajmHoda\Agents\PilotAgent;
use App\Services\NajmHoda\Agents\StewardAgent;
use App\Services\NajmHoda\Agents\GuideAgent;
use App\Services\NajmHoda\Agents\ArchitectAgent;
use App\Services\NajmHoda\MockModeService;
use App\Services\NajmHoda\CodeScanner\CodeScannerService;
use App\Services\NajmHoda\CodeScanner\CodeAnalyzerService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;

/**
 * سرویس پرووایدر نجم‌هدا
 * 
 * مسئول ثبت و راه‌اندازی تمام سرویس‌های نجم‌هدا
 */
class NajmHodaServiceProvider extends ServiceProvider
{
    /**
     * ثبت سرویس‌ها
     */
    public function register(): void
    {
        // ثبت Singleton برای هر عامل
        $this->app->singleton(EngineerAgent::class, function ($app) {
            return new EngineerAgent();
        });

        $this->app->singleton(PilotAgent::class, function ($app) {
            return new PilotAgent();
        });

        $this->app->singleton(StewardAgent::class, function ($app) {
            return new StewardAgent();
        });

        $this->app->singleton(GuideAgent::class, function ($app) {
            return new GuideAgent();
        });

        $this->app->singleton(ArchitectAgent::class, function ($app) {
            return new ArchitectAgent();
        });

        // ثبت Mock Mode Service
        $this->app->singleton(MockModeService::class, function ($app) {
            return new MockModeService();
        });

        // ثبت Code Scanner
        $this->app->singleton(CodeScannerService::class, function ($app) {
            return new CodeScannerService();
        });

        // ثبت Code Analyzer
        $this->app->singleton(CodeAnalyzerService::class, function ($app) {
            return new CodeAnalyzerService();
        });

        // ثبت Orchestrator
        $this->app->singleton(NajmHodaOrchestrator::class, function ($app) {
            return new NajmHodaOrchestrator();
        });
    }

    /**
     * Bootstrap سرویس‌ها
     */
    public function boot(): void
    {
        // ایجاد دایرکتوری Knowledge Base
        $this->createKnowledgeBaseDirectory();
        
        // تنظیم لاگ کانال
        $this->setupLoggingChannel();
        
        // ثبت Event Listeners
        $this->registerEventListeners();
        
        Log::info('نجم‌هدا راه‌اندازی شد');
    }

    /**
     * ایجاد دایرکتوری Knowledge Base
     */
    protected function createKnowledgeBaseDirectory(): void
    {
        $knowledgePath = config('najm-hoda.knowledge_base_path');
        
        if (!file_exists($knowledgePath)) {
            mkdir($knowledgePath, 0755, true);
            $this->createDefaultKnowledgeFiles($knowledgePath);
        }
    }

    /**
     * ایجاد فایل‌های پیش‌فرض Knowledge Base
     */
    protected function createDefaultKnowledgeFiles(string $path): void
    {
        $files = [
            'project-info.md' => $this->getProjectInfo(),
            'vision.md' => $this->getVision(),
            'values.md' => $this->getValues(),
            'user-guide.md' => $this->getUserGuide(),
        ];

        foreach ($files as $filename => $content) {
            $filePath = "$path/$filename";
            if (!file_exists($filePath)) {
                file_put_contents($filePath, $content);
            }
        }
    }

    /**
     * تنظیم کانال لاگ
     */
    protected function setupLoggingChannel(): void
    {
        config([
            'logging.channels.najm_hoda' => [
                'driver' => 'daily',
                'path' => storage_path('logs/najm-hoda.log'),
                'level' => config('najm-hoda.logging.level', 'info'),
                'days' => 14,
            ],
        ]);
    }

    /**
     * ثبت Event Listeners
     */
    protected function registerEventListeners(): void
    {
        // مثال: وقتی کاربر جدید ثبت نام می‌کند
        \Event::listen(\Illuminate\Auth\Events\Registered::class, function ($event) {
            if (config('najm-hoda.auto_actions.user_responses')) {
                try {
                    $orchestrator = app(NajmHodaOrchestrator::class);
                    // می‌توانیم پیام خوش‌آمدگویی بفرستیم
                    Log::info('کاربر جدید ثبت نام کرد', ['user_id' => $event->user->id]);
                } catch (\Exception $e) {
                    Log::error('خطا در پردازش ثبت نام: ' . $e->getMessage());
                }
            }
        });
    }

    /**
     * محتوای پیش‌فرض اطلاعات پروژه
     */
    protected function getProjectInfo(): string
    {
        return <<<MD
# پروژه NewEarthCoop (ارثکوپ)

## خلاصه
ارثکوپ یک پلتفرم تعاونی اقتصادی مبتنی بر وب است که به کاربران امکان می‌دهد:
- در حراج‌های آنلاین شرکت کنند
- سرمایه‌گذاری عادلانه انجام دهند
- کیف پول دیجیتال داشته باشند
- در تصمیمات جمعی مشارکت کنند

## تکنولوژی
- **Backend**: Laravel 11
- **Frontend**: Vue.js 3, Bootstrap 5
- **Database**: MySQL 8.0
- **Cache**: Redis
- **Language**: فارسی (RTL)

## ویژگی‌های اصلی
1. سیستم احراز هویت امن
2. حراج‌های آنلاین با پیشنهاد قیمت
3. کیف پول دیجیتال
4. مدیریت دارایی‌ها
5. انجمن و گفتگوها
6. گزارش‌گیری مالی

## اهداف
- دموکراسی اقتصادی
- شفافیت کامل
- توزیع عادلانه ثروت
- توسعه پایدار

[این فایل را با اطلاعات دقیق‌تر پروژه تکمیل کنید]
MD;
    }

    /**
     * محتوای پیش‌فرض چشم‌انداز
     */
    protected function getVision(): string
    {
        return <<<MD
# چشم‌انداز ارثکوپ

دنیایی که در آن:
- همه افراد فرصت برابر برای سرمایه‌گذاری دارند
- ثروت به صورت عادلانه توزیع می‌شود
- تصمیمات به صورت جمعی و دموکراتیک گرفته می‌شوند
- شفافیت کامل در تمام تراکنش‌ها وجود دارد
- محیط زیست و توسعه پایدار محافظت می‌شود

[این فایل را تکمیل کنید]
MD;
    }

    /**
     * محتوای پیش‌فرض ارزش‌ها
     */
    protected function getValues(): string
    {
        return <<<MD
# ارزش‌های اصلی ارثکوپ

1. **شفافیت**: همه چیز قابل رؤیت و تأیید است
2. **عدالت**: فرصت‌های برابر برای همه
3. **مشارکت**: تصمیم‌گیری جمعی
4. **نوآوری**: پذیرش تغییر و بهبود مستمر
5. **پایداری**: فکر کردن به آینده

[این فایل را تکمیل کنید]
MD;
    }

    /**
     * محتوای پیش‌فرض راهنمای کاربر
     */
    protected function getUserGuide(): string
    {
        return <<<MD
# راهنمای کاربران ارثکوپ

## شروع کار
1. ثبت نام در سیستم
2. احراز هویت
3. شارژ کیف پول
4. شرکت در حراج‌ها

## سوالات متداول
[به‌روزرسانی شود]
MD;
    }
}
