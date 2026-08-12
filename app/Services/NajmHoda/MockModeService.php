<?php

namespace App\Services\NajmHoda;

/**
 * Mock Mode Service
 * 
 * سرویس شبیه‌سازی کامل برای تست بدون نیاز به API واقعی
 */
class MockModeService
{
    /**
     * دیتای شبیه‌سازی شده برای هر عامل
     */
    private array $mockResponses = [];

    public function __construct()
    {
        $this->initializeMockData();
    }

    /**
     * مقداردهی اولیه دیتاهای Mock
     */
    private function initializeMockData(): void
    {
        // پاسخ‌های مهندس (Engineer)
        $this->mockResponses['engineer'] = [
            'code_review' => [
                '✅ **کد قابل قبول است**',
                '',
                '### نکات مثبت:',
                '- ساختار کلاس منظم و خوانا',
                '- استفاده صحیح از Type Hinting',
                '- مستندسازی مناسب با PHPDoc',
                '',
                '### پیشنهادات بهبود:',
                '1. افزودن Unit Test برای متدهای کلیدی',
                '2. استفاده از Dependency Injection بجای new',
                '3. اضافه کردن Exception Handling در قسمت‌های حساس',
                '',
                '**امتیاز کلی:** 8/10',
            ],
            
            'bug_analysis' => [
                '🐛 **تحلیل باگ:**',
                '',
                '### علت احتمالی:',
                'مشکل از Null Reference Exception ناشی می‌شود.',
                '',
                '### راه‌حل:',
                '```php',
                'if ($user !== null && $user->hasPermission("admin")) {',
                '    // انجام عملیات',
                '}',
                '```',
                '',
                '### توصیه:',
                'اضافه کردن Null Check قبل از دسترسی به Property',
            ],
            
            'refactor' => [
                '♻️ **پیشنهاد Refactoring:**',
                '',
                '### کد فعلی:',
                '```php',
                'public function process($data) {',
                '    if ($data["type"] == "user") {',
                '        // process user',
                '    } else if ($data["type"] == "admin") {',
                '        // process admin',
                '    }',
                '}',
                '```',
                '',
                '### کد بهینه‌شده:',
                '```php',
                'public function process(array $data): void {',
                '    match($data["type"]) {',
                '        "user" => $this->processUser($data),',
                '        "admin" => $this->processAdmin($data),',
                '        default => throw new InvalidArgumentException()',
                '    };',
                '}',
                '```',
            ],
        ];

        // پاسخ‌های خلبان (Pilot)
        $this->mockResponses['pilot'] = [
            'implement_feature' => [
                '📋 **طرح پیاده‌سازی ویژگی جدید**',
                '',
                '### مرحله 1: آماده‌سازی (2 ساعت)',
                '- بررسی نیازمندی‌ها',
                '- طراحی Database Schema',
                '- ایجاد Migration',
                '',
                '### مرحله 2: Backend (4 ساعت)',
                '- ساخت Model و Controller',
                '- پیاده‌سازی Business Logic',
                '- ایجاد API Endpoints',
                '- نوشتن Unit Tests',
                '',
                '### مرحله 3: Frontend (3 ساعت)',
                '- طراحی UI/UX',
                '- پیاده‌سازی Components',
                '- اتصال به API',
                '',
                '### مرحله 4: Testing & Deploy (1 ساعت)',
                '- تست یکپارچه',
                '- رفع باگ‌ها',
                '- Deploy در محیط Production',
                '',
                '**زمان کل: 10 ساعت**',
            ],
            
            'task_breakdown' => [
                '📊 **شکستن Task به Subtask‌ها:**',
                '',
                '#### Task اصلی: سیستم نظرات کاربران',
                '',
                '1. **Database (30 دقیقه)**',
                '   - [ ] Migration برای جدول comments',
                '   - [ ] Foreign Keys و Indexes',
                '',
                '2. **Backend (2 ساعت)**',
                '   - [ ] Model: Comment.php',
                '   - [ ] Controller: CommentController.php',
                '   - [ ] Validation Rules',
                '   - [ ] API Routes',
                '',
                '3. **Frontend (1.5 ساعت)**',
                '   - [ ] Component: CommentList.vue',
                '   - [ ] Component: CommentForm.vue',
                '   - [ ] Styling با Tailwind',
                '',
                '4. **Testing (45 دقیقه)**',
                '   - [ ] Unit Test',
                '   - [ ] Feature Test',
                '   - [ ] Manual Testing',
            ],
        ];

        // پاسخ‌های مهماندار (Steward)
        $this->mockResponses['steward'] = [
            'general_help' => [
                'سلام! 👋',
                '',
                'خوشحالم که می‌تونم کمکتون کنم.',
                '',
                '**من می‌تونم در این موارد کمک کنم:**',
                '- پاسخ به سوالات عمومی',
                '- راهنمایی در استفاده از سیستم',
                '- هماهنگی با عوامل دیگر برای کارهای تخصصی',
                '',
                'چطور می‌تونم کمکتون کنم؟ 😊',
            ],
            
            'user_support' => [
                '🤝 **پشتیبانی کاربر**',
                '',
                'متوجه مشکلتون شدم. بذارید کمک کنم:',
                '',
                '### راه‌حل‌های پیشنهادی:',
                '1. پاک کردن Cache مرورگر',
                '2. خروج و ورود مجدد',
                '3. بررسی اتصال اینترنت',
                '',
                'اگر مشکل حل نشد، با تیم پشتیبانی تماس بگیرید.',
                '',
                '📞 تماس: +98-9394765289',
                '📧 ایمیل: support@newearthcoop.com',
            ],
        ];

        // پاسخ‌های راهنما (Guide)
        $this->mockResponses['guide'] = [
            'documentation' => [
                '📚 **مستندات سیستم**',
                '',
                '### راهنمای شروع سریع:',
                '',
                '#### نصب و راه‌اندازی:',
                '```bash',
                'git clone https://github.com/newearthcoop/project.git',
                'cd project',
                'composer install',
                'npm install',
                'cp .env.example .env',
                'php artisan key:generate',
                'php artisan migrate',
                'php artisan serve',
                '```',
                '',
                '#### ساختار پروژه:',
                '- `app/` - منطق برنامه',
                '- `routes/` - مسیرها',
                '- `resources/` - Views و Assets',
                '- `database/` - Migrations و Seeders',
                '',
                '#### دستورات کاربردی:',
                '- `php artisan migrate` - اجرای migrations',
                '- `php artisan db:seed` - اجرای seeders',
                '- `php artisan test` - اجرای تست‌ها',
            ],
            
            'tutorial' => [
                '🎓 **آموزش گام به گام**',
                '',
                '### چگونه یک CRUD ساده بسازیم؟',
                '',
                '**گام 1: ساخت Model**',
                '```bash',
                'php artisan make:model Product -mcr',
                '```',
                '',
                '**گام 2: تعریف Migration**',
                '```php',
                'Schema::create("products", function (Blueprint $table) {',
                '    $table->id();',
                '    $table->string("name");',
                '    $table->decimal("price", 8, 2);',
                '    $table->timestamps();',
                '});',
                '```',
                '',
                '**گام 3: ساخت Controller**',
                '```php',
                'class ProductController extends Controller {',
                '    public function index() {',
                '        return Product::all();',
                '    }',
                '}',
                '```',
            ],
        ];

        // پاسخ‌های معمار (Architect)
        $this->mockResponses['architect'] = [
            'need_analysis' => [
                '🔍 **تحلیل نیاز**',
                '',
                '### خلاصه درخواست:',
                'کاربر نیاز به یک سیستم مدیریت محتوا دارد.',
                '',
                '### بررسی عوامل فعلی:',
                '❌ Engineer - متخصص کد، نه طراحی سیستم',
                '❌ Pilot - پیاده‌سازی، نه تحلیل',
                '❌ Steward - پشتیبانی، نه توسعه',
                '❌ Guide - آموزش، نه ساخت',
                '',
                '### نتیجه:',
                '✅ نیاز به عامل جدید: **ContentManager**',
                '',
                '### پیشنهاد:',
                'ساخت عامل تخصصی با این قابلیت‌ها:',
                '- مدیریت مقالات و پست‌ها',
                '- دسته‌بندی محتوا',
                '- سئو و بهینه‌سازی',
            ],
            
            'agent_design' => [
                '🎨 **طراحی عامل جدید**',
                '',
                '```php',
                'class ContentManagerAgent extends BaseAgent',
                '{',
                '    protected string $role = "content_manager";',
                '    protected string $persianName = "مدیر محتوا";',
                '    ',
                '    protected array $expertise = [',
                '        "مدیریت محتوا",',
                '        "SEO",',
                '        "بهینه‌سازی متن",',
                '        "برنامه‌ریزی انتشار"',
                '    ];',
                '    ',
                '    public function createContent(array $data): array',
                '    {',
                '        // پیاده‌سازی',
                '    }',
                '    ',
                '    public function optimizeSEO(string $content): array',
                '    {',
                '        // پیاده‌سازی',
                '    }',
                '}',
                '```',
                '',
                '### قابلیت‌ها:',
                '- ✅ ساخت محتوای خودکار',
                '- ✅ بهینه‌سازی SEO',
                '- ✅ تحلیل محتوای رقبا',
                '- ✅ برنامه‌ریزی انتشار',
            ],
        ];
    }

    /**
     * دریافت پاسخ Mock برای عامل مشخص
     */
    public function getResponse(string $agent, string $context, string $prompt): string
    {
        // تشخیص نوع درخواست
        $requestType = $this->detectRequestType($prompt);
        
        // دریافت پاسخ مناسب
        $response = $this->mockResponses[$agent][$requestType] ?? 
                    $this->getDefaultResponse($agent, $prompt);
        
        // اگر آرایه است، تبدیل به رشته
        if (is_array($response)) {
            $response = implode("\n", $response);
        }
        
        // اضافه کردن هدر Mock Mode
        $mockHeader = "⚙️ **[Mock Mode - تست بدون API]**\n\n";
        
        return $mockHeader . $response;
    }

    /**
     * تشخیص نوع درخواست
     */
    private function detectRequestType(string $prompt): string
    {
        $prompt = strtolower($prompt);
        
        // کلمات کلیدی برای هر نوع درخواست
        $keywords = [
            'code_review' => ['review', 'بررسی', 'کد', 'code', 'چک'],
            'bug_analysis' => ['bug', 'error', 'باگ', 'خطا', 'مشکل'],
            'refactor' => ['refactor', 'بازنویسی', 'بهینه', 'optimize'],
            'implement_feature' => ['implement', 'feature', 'پیاده', 'ویژگی'],
            'task_breakdown' => ['task', 'تسک', 'تقسیم', 'breakdown'],
            'general_help' => ['help', 'کمک', 'راهنما', 'سوال'],
            'user_support' => ['support', 'پشتیبانی', 'مشکل'],
            'documentation' => ['document', 'مستند', 'doc', 'راهنما'],
            'tutorial' => ['tutorial', 'آموزش', 'یاد', 'learn'],
            'need_analysis' => ['نیاز', 'need', 'analysis', 'تحلیل'],
            'agent_design' => ['design', 'طراحی', 'agent', 'عامل'],
        ];
        
        foreach ($keywords as $type => $words) {
            foreach ($words as $word) {
                if (str_contains($prompt, $word)) {
                    return $type;
                }
            }
        }
        
        return 'general_help';
    }

    /**
     * پاسخ پیش‌فرض
     */
    private function getDefaultResponse(string $agent, string $prompt): string
    {
        $agentNames = [
            'engineer' => 'مهندس',
            'pilot' => 'خلبان',
            'steward' => 'مهماندار',
            'guide' => 'راهنما',
            'architect' => 'معمار',
        ];
        
        $name = $agentNames[$agent] ?? $agent;
        
        return "سلام! من **{$name}** هستم.\n\n" .
               "پیام شما دریافت شد:\n> {$prompt}\n\n" .
               "در حال آماده‌سازی پاسخ تخصصی...\n\n" .
               "_این یک پاسخ Mock است برای تست سیستم_";
    }

    /**
     * ساخت گزارش عملکرد Mock
     */
    public function generatePerformanceReport(): array
    {
        return [
            'total_requests' => rand(100, 500),
            'success_rate' => rand(85, 99) . '%',
            'avg_response_time' => rand(200, 800) . 'ms',
            'mock_mode' => true,
            'agents_activity' => [
                'engineer' => rand(50, 150),
                'pilot' => rand(30, 100),
                'steward' => rand(40, 120),
                'guide' => rand(20, 80),
                'architect' => rand(10, 50),
            ],
        ];
    }

    /**
     * ساخت Conversation Mock
     */
    public function generateMockConversation(int $userId): array
    {
        $agents = ['engineer', 'pilot', 'steward', 'guide', 'architect'];
        
        return [
            'id' => rand(1000, 9999),
            'user_id' => $userId,
            'agent' => $agents[array_rand($agents)],
            'title' => 'مکالمه تستی - Mock Mode',
            'status' => 'active',
            'messages_count' => rand(5, 20),
            'created_at' => now()->subHours(rand(1, 48)),
            'updated_at' => now()->subMinutes(rand(1, 60)),
        ];
    }

    /**
     * چک کردن وضعیت Mock Mode
     */
    public function isMockMode(): bool
    {
        return config('najm-hoda.mock_mode', false);
    }

    /**
     * فعال/غیرفعال کردن Mock Mode
     */
    public function setMockMode(bool $enabled): void
    {
        config(['najm-hoda.mock_mode' => $enabled]);
    }
}
