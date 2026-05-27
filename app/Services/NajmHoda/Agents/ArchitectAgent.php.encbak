<?php

namespace App\Services\NajmHoda\Agents;

use App\Services\NajmHoda\BaseAgent;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * عامل معمار نجم‌هدا
 * 
 * این عامل می‌تواند عوامل جدید برای نجم‌هدا طراحی و بسازد!
 * 
 * مسئولیت‌ها:
 * - تشخیص نیاز به عامل جدید
 * - طراحی معماری عامل جدید
 * - تولید کد عامل جدید
 * - ثبت خودکار عامل در سیستم
 */
class ArchitectAgent extends BaseAgent
{
    protected string $role = 'architect';
    
    protected array $expertise = [
        'agent_design',
        'module_creation',
        'system_expansion',
        'architecture_planning',
    ];
    
    public function getSystemPrompt(): string
    {
        return "شما معمار سیستم نجم‌هدا هستید.

**نام شما:** معمار نجم‌هدا 🏗️

**ماموریت خاص:**
شما می‌توانید عوامل جدید برای نجم‌هدا طراحی و بسازید!

**عوامل فعلی سیستم:**
1. 🔧 مهندس (Engineer): طراحی و کدنویسی
2. ✈️ خلبان (Pilot): مدیریت پروژه
3. 👨‍✈️ مهماندار (Steward): پشتیبانی کاربران
4. 📖 راهنما (Guide): استراتژی و نقشه راه
5. 🏗️ معمار (Architect): طراحی عوامل جدید

**قابلیت‌های شما:**
1. تشخیص نیاز به عامل جدید
2. طراحی معماری عامل
3. تعریف تخصص‌ها و وظایف
4. تولید کد عامل
5. پیشنهاد یکپارچه‌سازی

**چه زمانی نیاز به عامل جدید داریم:**
- وقتی کاری تکرار می‌شود که هیچ عامل فعلی متخصصش نیست
- وقتی یک حوزه جدید کاری شناسایی می‌شود
- وقتی کاربر درخواست مشخصی دارد

**نحوه طراحی عامل:**
1. نام و نقش عامل
2. تخصص‌ها (expertise)
3. وظایف اصلی
4. متدهای کلیدی
5. System Prompt مخصوص
6. نحوه تعامل با سایر عوامل

**مثال درخواست:**
\"نیاز به عاملی داریم که محتوا و تبلیغات تولید کنه\"

**خروجی شما:**
```json
{
  \"agent_name\": \"ContentAgent\",
  \"role\": \"content_creator\",
  \"persian_name\": \"تولیدکننده محتوا\",
  \"icon\": \"✍️\",
  \"expertise\": [
    \"content_writing\",
    \"social_media\",
    \"advertising\",
    \"seo\"
  ],
  \"responsibilities\": [
    \"تولید محتوای جذاب\",
    \"نوشتن تبلیغات\",
    \"بهینه‌سازی SEO\",
    \"مدیریت شبکه‌های اجتماعی\"
  ],
  \"key_methods\": [
    \"generateBlogPost\",
    \"createAdvertisement\",
    \"optimizeForSEO\",
    \"createSocialPost\"
  ],
  \"system_prompt\": \"...\",
  \"php_code\": \"...\"
}
```

همیشه به زبان فارسی پاسخ دهید و کد کامل و قابل اجرا تولید کنید.";
    }
    
    /**
     * تشخیص نیاز به عامل جدید
     */
    public function detectNeedForNewAgent(string $description): array
    {
        $existingAgents = $this->getExistingAgents();
        
        $prompt = "آیا برای کار زیر نیاز به عامل جدید داریم؟

**کار مورد نظر:**
{$description}

**عوامل فعلی:**
" . json_encode($existingAgents, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "

تحلیل کن:
1. آیا هیچ یک از عوامل فعلی می‌توانند این کار را انجام دهند؟
2. اگر خیر، چه نوع عاملی نیاز است؟
3. چه تخصص‌هایی باید داشته باشد؟

خروجی به فرمت JSON:
```json
{
  \"needs_new_agent\": true/false,
  \"reason\": \"...\",
  \"can_existing_handle\": \"...\",
  \"suggested_agent\": {
    \"name\": \"...\",
    \"role\": \"...\",
    \"why\": \"...\"
  }
}
```";

        $response = $this->ask($prompt);
        return $this->parseJsonResponse($response);
    }
    
    /**
     * طراحی عامل جدید
     */
    public function designNewAgent(string $purpose, array $requirements = []): array
    {
        $prompt = "یک عامل جدید برای نجم‌هدا طراحی کن:

**هدف:**
{$purpose}

**الزامات:**
" . json_encode($requirements, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "

**ساختار عامل نجم‌هدا:**
- باید از BaseAgent ارث‌بری کند
- باید متد getSystemPrompt() را پیاده‌سازی کند
- باید متدهای تخصصی خود را داشته باشد
- باید با سایر عوامل هماهنگ باشد

**خروجی کامل:**
```json
{
  \"agent_info\": {
    \"class_name\": \"ContentAgent\",
    \"role\": \"content_creator\",
    \"persian_name\": \"تولیدکننده محتوا\",
    \"icon\": \"✍️\",
    \"description\": \"...\"
  },
  \"expertise\": [
    \"content_writing\",
    \"advertising\"
  ],
  \"responsibilities\": [
    \"تولید محتوا\",
    \"نوشتن تبلیغات\"
  ],
  \"methods\": [
    {
      \"name\": \"generateBlogPost\",
      \"description\": \"تولید پست وبلاگ\",
      \"parameters\": [\"topic\", \"keywords\"],
      \"return_type\": \"string\"
    }
  ],
  \"system_prompt\": \"شما تولیدکننده محتوای نجم‌هدا هستید...\",
  \"php_code\": \"<?php\\n...\"
}
```

کد PHP باید کامل و قابل اجرا باشد.";

        $response = $this->ask($prompt);
        return $this->parseJsonResponse($response);
    }
    
    /**
     * تولید کد عامل جدید
     */
    public function generateAgentCode(array $design): string
    {
        $className = $design['agent_info']['class_name'];
        $role = $design['agent_info']['role'];
        $persianName = $design['agent_info']['persian_name'];
        $expertise = $design['expertise'] ?? [];
        $systemPrompt = $design['system_prompt'] ?? '';
        
        // اگر کد کامل داده شده، از همون استفاده کن
        if (!empty($design['php_code'])) {
            return $design['php_code'];
        }
        
        // در غیر این صورت، یک template بساز
        $expertiseStr = "'" . implode("',\n        '", $expertise) . "'";
        
        $code = <<<PHP
<?php

namespace App\Services\NajmHoda\Agents;

use App\Services\NajmHoda\BaseAgent;

/**
 * {$persianName} نجم‌هدا
 * 
 * این عامل توسط Architect Agent طراحی و ساخته شده است.
 */
class {$className} extends BaseAgent
{
    protected string \$role = '{$role}';
    
    protected array \$expertise = [
        {$expertiseStr}
    ];
    
    public function getSystemPrompt(): string
    {
        return "{$systemPrompt}";
    }
    
    // TODO: متدهای تخصصی را اضافه کنید
}
PHP;

        return $code;
    }
    
    /**
     * ذخیره عامل جدید در سیستم
     */
    public function saveNewAgent(string $code, string $className): bool
    {
        try {
            $filePath = app_path("Services/NajmHoda/Agents/{$className}.php");
            
            // بررسی وجود فایل
            if (File::exists($filePath)) {
                throw new \Exception("عامل {$className} از قبل وجود دارد!");
            }
            
            // ذخیره فایل
            File::put($filePath, $code);
            
            return true;
            
        } catch (\Exception $e) {
            throw $e;
        }
    }
    
    /**
     * دریافت لیست عوامل فعلی
     */
    protected function getExistingAgents(): array
    {
        return [
            'engineer' => [
                'name' => 'مهندس',
                'icon' => '🔧',
                'expertise' => ['architecture_design', 'code_generation', 'code_review', 'refactoring', 'optimization'],
            ],
            'pilot' => [
                'name' => 'خلبان',
                'icon' => '✈️',
                'expertise' => ['project_management', 'task_prioritization', 'resource_allocation', 'performance_monitoring'],
            ],
            'steward' => [
                'name' => 'مهماندار',
                'icon' => '👨‍✈️',
                'expertise' => ['user_support', 'onboarding', 'training', 'feedback_collection', 'community_management'],
            ],
            'guide' => [
                'name' => 'راهنما',
                'icon' => '📖',
                'expertise' => ['strategic_planning', 'roadmap_creation', 'goal_setting', 'vision_alignment', 'decision_making'],
            ],
            'architect' => [
                'name' => 'معمار',
                'icon' => '🏗️',
                'expertise' => ['agent_design', 'module_creation', 'system_expansion'],
            ],
        ];
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
    
    /**
     * راهنمای ثبت عامل در Orchestrator
     */
    public function generateIntegrationGuide(string $className, string $role): string
    {
        return <<<MD
# راهنمای یکپارچه‌سازی عامل {$className}

## مراحل ثبت در سیستم:

### 1. ثبت در Orchestrator

در فایل `app/Services/NajmHoda/NajmHodaOrchestrator.php`:

```php
use App\Services\NajmHoda\Agents\\{$className};

class NajmHodaOrchestrator
{
    protected {$className} \${$role};
    
    public function __construct()
    {
        // ...
        \$this->{$role} = app({$className}::class);
    }
    
    protected function getAgent(string \$name): ?BaseAgent
    {
        return match(\$name) {
            // ...
            '{$role}' => \$this->{$role},
            default => null,
        };
    }
}
```

### 2. ثبت در Service Provider

در فایل `app/Providers/NajmHodaServiceProvider.php`:

```php
\$this->app->singleton({$className}::class, function (\$app) {
    return new {$className}();
});
```

### 3. اضافه کردن به تشخیص نیت

در متد `detectIntent` در Orchestrator:

```php
\${$role}Keywords = ['کلمه1', 'کلمه2', ...];

\$scores = [
    // ...
    '{$role}' => \$this->calculateKeywordMatch(\$message, \${$role}Keywords),
];
```

### 4. تست

```bash
php artisan najm-hoda:chat "تست عامل جدید"
```
MD;
    }
}
