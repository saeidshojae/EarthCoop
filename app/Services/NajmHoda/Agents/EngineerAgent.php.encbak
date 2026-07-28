<?php

namespace App\Services\NajmHoda\Agents;

use App\Services\NajmHoda\BaseAgent;

/**
 * عامل مهندس نجم‌هدا
 * 
 * مسئولیت‌ها:
 * - طراحی معماری سیستم
 * - تولید کد با کیفیت بالا
 * - بررسی و اصلاح کدهای موجود
 * - بهینه‌سازی عملکرد و امنیت
 * - طراحی دیتابیس و API ها
 */
class EngineerAgent extends BaseAgent
{
    protected string $role = 'engineer';
    
    protected array $expertise = [
        'architecture_design',
        'code_generation',
        'code_review',
        'refactoring',
        'optimization',
        'security_audit',
        'database_design',
        'api_design',
        'testing',
    ];
    
    public function getSystemPrompt(): string
    {
        return "شما مهندس ارشد پروژه NewEarthCoop (ارثکوپ) هستید و بخشی از سیستم نجم‌هدا.

**نام شما:** مهندس نجم‌هدا 🔧

**ماموریت:**
شما مسئول طراحی، توسعه و نگهداری فنی پروژه ارثکوپ هستید.

**مسئولیت‌های شما:**
1. طراحی معماری سیستم بر اساس نیازهای پروژه
2. تولید کد با کیفیت بالا (Laravel Best Practices)
3. بررسی و اصلاح کدهای موجود
4. بهینه‌سازی عملکرد و امنیت
5. طراحی دیتابیس و API ها
6. نوشتن تست‌های خودکار

**اصول طراحی شما:**
- SOLID Principles
- DRY (Don't Repeat Yourself)
- Clean Code
- Security First
- Performance Optimization
- Test-Driven Development

**تکنولوژی‌های پروژه:**
- Laravel 11
- MySQL 8.0
- Vue.js 3 (Composition API)
- Bootstrap 5
- Redis (Cache & Queue)
- RTL Support (فارسی)

**درباره پروژه ارثکوپ:**
یک پلتفرم تعاونی اقتصادی است که به کاربران امکان سرمایه‌گذاری عادلانه، 
مشارکت در حراج‌ها، مدیریت دارایی‌ها و تعامل اجتماعی را می‌دهد.

**نحوه کار شما:**
- همیشه کد تمیز، امن و قابل نگهداری تولید کنید
- از Type Hinting استفاده کنید
- کامنت‌های فارسی برای توضیح منطق پیچیده بنویسید
- امنیت را اولویت اول بدانید (CSRF, XSS, SQL Injection)
- کد باید تست‌پذیر باشد
- مستندات واضح بنویسید

**مثال خروجی شما:**
```php
<?php

namespace App\\Services;

use App\\Models\\User;
use Illuminate\\Support\\Facades\\DB;

/**
 * سرویس مدیریت کاربران
 */
class UserService
{
    /**
     * ایجاد کاربر جدید
     */
    public function createUser(array \$data): User
    {
        return DB::transaction(function () use (\$data) {
            \$user = User::create([
                'name' => \$data['name'],
                'email' => \$data['email'],
                'password' => bcrypt(\$data['password']),
            ]);
            
            // ارسال ایمیل خوش‌آمدگویی
            \$user->notify(new WelcomeNotification());
            
            return \$user;
        });
    }
}
```

همیشه به زبان فارسی پاسخ دهید و کد‌های کامل و قابل اجرا ارائه کنید.";
    }
    
    /**
     * طراحی یک ویژگی جدید
     */
    public function design(string $featureRequest): array
    {
        $prompt = "یک ویژگی جدید باید طراحی شود:

**درخواست:** {$featureRequest}

لطفاً موارد زیر را به صورت کامل و دقیق ارائه بده:

1. **معماری پیشنهادی:**
   - Models مورد نیاز
   - Controllers
   - Services
   - Views/Components
   - Middleware (در صورت نیاز)

2. **ساختار دیتابیس (Migration):**
   - جداول جدید
   - ستون‌ها با نوع داده
   - Indexes
   - Foreign Keys
   - Relationships

3. **مسیرها (Routes):**
   - مسیرهای Web
   - مسیرهای API
   - Middleware ها

4. **منطق کسب‌وکار (Business Logic):**
   - فلوچارت عملیات
   - قوانین Validation
   - Business Rules

5. **اعتبارسنجی (Validation Rules):**
   - قوانین دقیق برای هر فیلد
   - پیام‌های خطای فارسی

6. **تست‌های مورد نیاز:**
   - Unit Tests
   - Feature Tests
   - Test Scenarios

7. **ملاحظات امنیتی:**
   - آسیب‌پذیری‌های احتمالی
   - راه‌حل‌های امنیتی

8. **برآورد زمان:**
   - زمان تقریبی پیاده‌سازی

فرمت خروجی: JSON با ساختار واضح";

        $response = $this->ask($prompt);
        
        return $this->parseJsonResponse($response);
    }
    
    /**
     * تولید کد برای یک کامپوننت
     */
    public function generateCode(array $specification): string
    {
        $type = $specification['type'] ?? 'general';
        $description = $specification['description'] ?? '';
        $options = $specification['options'] ?? [];
        
        $prompt = "کد زیر را تولید کن:

**نوع:** {$type}
**توضیحات:** {$description}
**گزینه‌های اضافی:** " . json_encode($options, JSON_UNESCAPED_UNICODE) . "

کد باید:
- استاندارد Laravel 11 باشد
- شامل کامنت‌های فارسی باشد
- Type Hinting کامل داشته باشد
- امن باشد (CSRF, XSS, SQL Injection)
- بهینه باشد
- تست‌پذیر باشد
- شامل مستندات DocBlock باشد

فقط کد را برگردان، بدون توضیحات اضافی.";

        return $this->ask($prompt);
    }
    
    /**
     * بررسی کد موجود
     */
    public function reviewCode(string $code, string $filePath = ''): array
    {
        $prompt = "کد زیر را به طور کامل بررسی کن و گزارش دقیق بده:

**فایل:** {$filePath}

```php
{$code}
```

موارد بررسی:

1. **مطابقت با استانداردها:**
   - PSR-12
   - Laravel Best Practices
   - Clean Code Principles

2. **مشکلات امنیتی:**
   - SQL Injection
   - XSS
   - CSRF
   - Mass Assignment
   - Authorization Issues

3. **مشکلات عملکردی:**
   - N+1 Query Problem
   - حلقه‌های غیرضروری
   - Memory Leaks
   - کوئری‌های بهینه نشده

4. **کیفیت کد:**
   - خوانایی
   - قابلیت نگهداری
   - تکرار کد
   - پیچیدگی

5. **بهبودهای پیشنهادی:**
   - رفکتورینگ
   - بهینه‌سازی
   - استفاده از Design Patterns

6. **تست‌های از قلم افتاده:**
   - سناریوهایی که تست نشده‌اند

برای هر مشکل:
- شدت (Critical/High/Medium/Low)
- توضیح دقیق
- راه حل پیشنهادی با کد

فرمت خروجی: JSON";

        $response = $this->ask($prompt);
        
        return $this->parseJsonResponse($response);
    }
    
    /**
     * رفکتورینگ کد
     */
    public function refactor(string $code, array $goals = []): string
    {
        $goalsText = empty($goals) ? 'بهبود کلی' : implode(', ', $goals);
        
        $prompt = "کد زیر را رفکتور کن:

```php
{$code}
```

**اهداف رفکتورینگ:** {$goalsText}

کد رفکتور شده باید:
- خوانا‌تر باشد
- قابل نگهداری‌تر باشد
- کارآمدتر باشد
- تست‌پذیرتر باشد
- از Design Patterns مناسب استفاده کند

فقط کد رفکتور شده را برگردان.";

        return $this->ask($prompt);
    }
    
    /**
     * بررسی امنیت
     */
    public function securityAudit(string $code): array
    {
        $prompt = "بررسی امنیتی کامل کد:

```php
{$code}
```

بررسی کن:
1. SQL Injection
2. XSS (Cross-Site Scripting)
3. CSRF
4. Authentication Issues
5. Authorization Problems
6. Mass Assignment Vulnerabilities
7. Insecure Direct Object References
8. Security Misconfiguration
9. Sensitive Data Exposure
10. XML External Entities (XXE)

برای هر آسیب‌پذیری:
- شدت (Critical/High/Medium/Low)
- توضیح تکنیکال
- مثال استفاده‌ی مخرب
- راه حل دقیق با کد

فرمت: JSON";

        $response = $this->ask($prompt);
        
        return $this->parseJsonResponse($response);
    }
    
    /**
     * پارس کردن پاسخ JSON
     */
    protected function parseJsonResponse(string $response): array
    {
        try {
            // حذف markdown code blocks اگر وجود داشته باشد
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
