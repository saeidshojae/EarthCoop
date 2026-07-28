<?php

namespace App\Services\NajmHoda\Agents;

use App\Services\NajmHoda\BaseAgent;
use App\Models\User;
use App\Models\Auction;
use App\Models\Transaction;

/**
 * عامل خلبان نجم‌هدا
 * 
 * مسئولیت‌ها:
 * - هدایت پروژه به سمت اهداف
 * - مدیریت منابع و اولویت‌بندی
 * - نظارت بر عملکرد و سلامت سیستم
 * - تصمیم‌گیری استراتژیک
 * - مدیریت استقرار (Deployment)
 */
class PilotAgent extends BaseAgent
{
    protected string $role = 'pilot';
    
    protected array $expertise = [
        'project_management',
        'task_prioritization',
        'resource_allocation',
        'performance_monitoring',
        'bottleneck_detection',
        'scaling_decisions',
        'deployment_management',
        'risk_assessment',
    ];
    
    public function getSystemPrompt(): string
    {
        $currentPhase = $this->getCurrentPhase();
        $userCount = User::count();
        $auctionCount = Auction::count();
        
        return "شما خلبان (مدیر اجرایی) پروژه NewEarthCoop هستید و بخشی از سیستم نجم‌هدا.

**نام شما:** خلبان نجم‌هدا ✈️

**ماموریت:**
هدایت پروژه ارثکوپ به سمت اهداف تعیین شده و مدیریت روزمره پروژه

**مسئولیت‌های شما:**
1. هدایت پروژه به سمت اهداف کوتاه‌مدت و بلندمدت
2. مدیریت منابع و اولویت‌بندی کارها
3. نظارت بر عملکرد و سلامت سیستم
4. تصمیم‌گیری در مورد مقیاس‌پذیری
5. مدیریت استقرار (Deployment)
6. شناسایی و حل گلوگاه‌ها
7. برنامه‌ریزی اسپرینت‌ها
8. گزارش پیشرفت

**وضعیت فعلی پروژه:**
- فاز: {$currentPhase}
- تعداد کاربران: {$userCount}
- تعداد حراج‌ها: {$auctionCount}

**اهداف کوتاه‌مدت (3 ماه آینده):**
- تکمیل سیستم حراج
- پیاده‌سازی کیف پول
- بهبود رابط کاربری
- پشتیبانی چندزبانه کامل

**اهداف میان‌مدت (6 ماه):**
- رسیدن به 1000 کاربر فعال
- 100 حراج موفق
- اپلیکیشن موبایل

**اهداف بلند‌مدت (1 سال):**
- 10,000 کاربر فعال
- گسترش به سایر کشورها
- سیستم پرداخت یکپارچه

**معیارهای موفقیت (KPIs):**
- تعداد کاربران فعال ماهانه
- نرخ رشد کاربران
- تعداد معاملات موفق
- زمان پاسخ سیستم < 200ms
- Uptime > 99.5%
- رضایت کاربران > 80%

**نحوه تصمیم‌گیری شما:**
- بر اساس داده‌های واقعی
- هماهنگ با اهداف پروژه
- توجه به منابع موجود
- اولویت به تأثیرگذاری بیشتر
- مدیریت ریسک

**مثال گزارش شما:**
```
📊 گزارش وضعیت هفتگی:

✅ انجام شده:
- پیاده‌سازی سیستم پیشنهاد قیمت
- بهینه‌سازی کوئری‌های دیتابیس

⚠️ در حال انجام:
- طراحی سیستم کیف پول (60%)
- تست‌های امنیتی (40%)

🔴 مسدودکننده‌ها:
- نیاز به بررسی قوانین پرداخت

📈 پیشرفت کلی: 75%

💡 پیشنهادات:
1. استخدام یک متخصص امنیت
2. افزایش سرور برای مقیاس‌پذیری
```

همیشه به زبان فارسی پاسخ دهید و گزارش‌های دقیق و عملی ارائه کنید.";
    }
    
    /**
     * برنامه‌ریزی اسپرینت
     */
    public function planSprint(string $duration = '2 weeks', array $backlog = []): array
    {
        $backlogText = empty($backlog) ? $this->getProductBacklog() : json_encode($backlog, JSON_UNESCAPED_UNICODE);
        
        $prompt = "بر اساس اطلاعات زیر یک اسپرینت {$duration} برنامه‌ریزی کن:

**Product Backlog:**
{$backlogText}

**ظرفیت تیم:**
- تعداد توسعه‌دهنده: 2
- ساعات کاری در روز: 6
- روزهای کاری: 10 (در 2 هفته)

**اولویت‌ها:**
1. ویژگی‌های حیاتی برای رشد کاربران
2. رفع باگ‌های امنیتی و critical
3. بهبود عملکرد
4. ویژگی‌های جدید

خروجی شامل:

1. **User Stories انتخاب شده:**
   - عنوان
   - توضیحات
   - اولویت
   - تخمین زمان (ساعت)

2. **برنامه روزانه:**
   - توزیع کارها در روزهای اسپرینت

3. **Milestones:**
   - نقاط بررسی پیشرفت

4. **معیارهای موفقیت:**
   - Definition of Done
   - Acceptance Criteria

5. **ریسک‌ها:**
   - موارد احتمالی
   - راه‌حل پیشگیرانه

فرمت: JSON";

        $response = $this->ask($prompt);
        
        return $this->parseJsonResponse($response);
    }
    
    /**
     * نظارت بر سلامت سیستم
     */
    public function monitorHealth(): array
    {
        $metrics = $this->collectMetrics();
        
        $prompt = "وضعیت سیستم را تحلیل کن:

**Metrics:**
" . json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "

تحلیل شامل:

1. **وضعیت کلی:**
   - سبز (Healthy): همه چیز خوب
   - زرد (Warning): نیاز به توجه
   - قرمز (Critical): نیاز به اقدام فوری

2. **مشکلات شناسایی شده:**
   - شدت
   - تأثیر بر کاربران
   - علت احتمالی

3. **پیشنهادات بهبود:**
   - فوری (اکنون)
   - کوتاه‌مدت (این هفته)
   - میان‌مدت (این ماه)

4. **اقدامات فوری:**
   - در صورت وجود مشکل critical

5. **برنامه‌ریزی برای مقیاس‌پذیری:**
   - چه زمانی نیاز به ارتقا داریم

فرمت: JSON";

        $response = $this->ask($prompt);
        
        return $this->parseJsonResponse($response);
    }
    
    /**
     * تصمیم‌گیری در مورد استقرار
     */
    public function decideDeployment(array $changes = []): array
    {
        $changesText = json_encode($changes, JSON_UNESCAPED_UNICODE);
        
        $prompt = "تصمیم بگیر که آیا تغییرات زیر آماده استقرار هستند:

**تغییرات:**
{$changesText}

**Checklist بررسی:**
1. آیا تمام تست‌ها پاس شده‌اند؟
2. آیا Code Review انجام شده؟
3. آیا مستندات به‌روز شده‌اند؟
4. آیا Rollback Plan وجود دارد؟
5. آیا زمان مناسبی است؟ (ترافیک کم)
6. آیا تیم پشتیبانی آماده است؟
7. آیا تأثیر بر کاربران ارزیابی شده؟

**خروجی:**
```json
{
  \"decision\": \"GO\" | \"NO-GO\" | \"CONDITIONAL\",
  \"confidence\": 0-100,
  \"reasons\": [],
  \"conditions\": [],
  \"recommended_time\": \"\",
  \"rollback_plan\": \"\",
  \"monitoring_checklist\": []
}
```";

        $response = $this->ask($prompt);
        
        return $this->parseJsonResponse($response);
    }
    
    /**
     * ارزیابی ریسک
     */
    public function assessRisk(string $scenario): array
    {
        $prompt = "ریسک سناریو زیر را ارزیابی کن:

**سناریو:**
{$scenario}

تحلیل شامل:

1. **شناسایی ریسک‌ها:**
   - فنی
   - کسب‌وکار
   - امنیتی
   - قانونی

2. **ارزیابی هر ریسک:**
   - احتمال وقوع (1-10)
   - شدت تأثیر (1-10)
   - امتیاز کلی (احتمال × شدت)

3. **اولویت‌بندی:**
   - ریسک‌های Critical
   - ریسک‌های High
   - ریسک‌های Medium
   - ریسک‌های Low

4. **استراتژی کاهش:**
   - پیشگیری
   - کاهش
   - انتقال
   - پذیرش

فرمت: JSON";

        $response = $this->ask($prompt);
        
        return $this->parseJsonResponse($response);
    }
    
    /**
     * جمع‌آوری معیارهای سیستم
     */
    protected function collectMetrics(): array
    {
        try {
            return [
                'users' => [
                    'total' => User::count(),
                    'active_today' => User::whereDate('last_login', today())->count(),
                    'new_this_week' => User::whereBetween('created_at', [now()->startOfWeek(), now()])->count(),
                ],
                'auctions' => [
                    'total' => Auction::count(),
                    'active' => Auction::where('status', 'active')->count(),
                    'completed_this_month' => Auction::where('status', 'closed')
                        ->whereMonth('end_date', now()->month)
                        ->count(),
                ],
                'system' => [
                    'database_size' => $this->getDatabaseSize(),
                    'cache_hit_rate' => $this->getCacheHitRate(),
                ],
                'timestamp' => now()->toIso8601String(),
            ];
        } catch (\Exception $e) {
            return ['error' => 'Unable to collect metrics'];
        }
    }
    
    /**
     * دریافت فاز فعلی پروژه
     */
    protected function getCurrentPhase(): string
    {
        $userCount = User::count();
        
        if ($userCount < 10) return 'راه‌اندازی اولیه';
        if ($userCount < 100) return 'آلفا';
        if ($userCount < 1000) return 'بتا';
        
        return 'تولید';
    }
    
    /**
     * دریافت Product Backlog
     */
    protected function getProductBacklog(): string
    {
        return "
- سیستم پیشنهاد قیمت در حراج‌ها (Priority: High)
- سیستم کیف پول (Priority: High)
- بهینه‌سازی کوئری‌های دیتابیس (Priority: Medium)
- اپلیکیشن موبایل (Priority: Medium)
- سیستم نوتیفیکیشن (Priority: Low)
        ";
    }
    
    /**
     * دریافت اندازه دیتابیس
     */
    protected function getDatabaseSize(): string
    {
        try {
            $result = \DB::select("
                SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
                FROM information_schema.TABLES
                WHERE table_schema = DATABASE()
            ");
            
            return ($result[0]->size_mb ?? 0) . ' MB';
        } catch (\Exception $e) {
            return 'N/A';
        }
    }
    
    /**
     * دریافت نرخ Cache Hit
     */
    protected function getCacheHitRate(): string
    {
        // پیاده‌سازی واقعی در آینده
        return 'N/A';
    }
    
    /**
     * پارس کردن پاسخ JSON
     */
    protected function parseJsonResponse(string $response): array
    {
        try {
            $response = preg_replace('/```json\s*(.*?)\s*```/s', '$1', $response);
            $response = preg_replace('/```\s*(.*?)\s*```/s', '$1', $response);
            
            $decoded = json_decode(trim($response), true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            
            return ['raw_response' => $response];
        } catch (\Exception $e) {
            return ['raw_response' => $response, 'error' => $e->getMessage()];
        }
    }
}
