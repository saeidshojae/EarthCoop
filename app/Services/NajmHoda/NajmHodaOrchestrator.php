<?php

namespace App\Services\NajmHoda;

use App\Services\NajmHoda\Agents\EngineerAgent;
use App\Services\NajmHoda\Agents\PilotAgent;
use App\Services\NajmHoda\Agents\StewardAgent;
use App\Services\NajmHoda\Agents\GuideAgent;
use App\Services\NajmHoda\Agents\ArchitectAgent;
use App\Services\NajmHoda\MockModeService;
use Illuminate\Support\Facades\Log;

/**
 * هماهنگ‌کننده مرکزی نجم‌هدا
 * 
 * این کلاس مسئول مدیریت و هماهنگی تمام عوامل نجم‌هدا است
 * و درخواست‌ها را به عامل مناسب مسیردهی می‌کند
 */
class NajmHodaOrchestrator
{
    protected EngineerAgent $engineer;
    protected PilotAgent $pilot;
    protected StewardAgent $steward;
    protected GuideAgent $guide;
    protected ArchitectAgent $architect;
    protected MockModeService $mockService;
    
    protected array $projectContext = [];
    protected string $currentPhase = '';
    
    public function __construct()
    {
        $this->engineer = app(EngineerAgent::class);
        $this->pilot = app(PilotAgent::class);
        $this->steward = app(StewardAgent::class);
        $this->guide = app(GuideAgent::class);
        $this->architect = app(ArchitectAgent::class);
        $this->mockService = app(MockModeService::class);
        
        $this->loadProjectContext();
        $this->detectCurrentPhase();
    }
    
    /**
     * مسیریابی درخواست به عامل مناسب
     * 
     * @param string $message پیام کاربر
     * @param array $context اطلاعات اضافی
     * @return array پاسخ شامل message و agent
     */
    public function route(string $message, array $context = []): array
    {
        // اگر عامل خاصی مشخص شده باشد
        if (isset($context['force_agent']) && $this->isValidAgent($context['force_agent'])) {
            return $this->handleByAgent($context['force_agent'], $message, $context);
        }
        
        // تشخیص خودکار نوع درخواست
        $intent = $this->detectIntent($message, $context);
        
        Log::info('نجم‌هدا - تشخیص نیت', [
            'message' => $message,
            'detected_intent' => $intent['type'],
            'confidence' => $intent['confidence'] ?? 0,
        ]);
        
        switch ($intent['type']) {
            case 'engineering':
                return $this->handleByAgent('engineer', $message, $context);
                
            case 'management':
                return $this->handleByAgent('pilot', $message, $context);
                
            case 'support':
                return $this->handleByAgent('steward', $message, $context);
                
            case 'guidance':
                return $this->handleByAgent('guide', $message, $context);
                
            case 'complex':
                return $this->handleComplexRequest($message, $context);
                
            default:
                // پیش‌فرض: مهماندار (برای کاربران عادی)
                return $this->handleByAgent('steward', $message, $context);
        }
    }
    
    /**
     * مدیریت درخواست توسط یک عامل خاص
     */
    protected function handleByAgent(string $agentName, string $message, array $context = []): array
    {
        // بررسی Mock Mode
        if (config('najm-hoda.mock_mode', false)) {
            return $this->handleMockResponse($agentName, $message, $context);
        }
        
        $agent = $this->getAgent($agentName);
        
        if (!$agent || !$agent->isEnabled()) {
            return [
                'success' => false,
                'message' => "عامل {$agentName} در دسترس نیست.",
                'agent' => 'system',
            ];
        }
        
        try {
            $response = $agent->ask($message, $context);
            
            return [
                'success' => true,
                'message' => $response,
                'agent' => $agentName,
                'agent_persian_name' => $agent->getPersianName(),
                'agent_icon' => $agent->getIcon(),
                'suggestions' => $this->generateSuggestions($agentName, $message),
            ];
            
        } catch (\Exception $e) {
            Log::error("خطا در عامل {$agentName}: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => "متأسفم، مشکلی پیش آمد. لطفاً دوباره تلاش کنید.",
                'agent' => $agentName,
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }
    
    /**
     * مدیریت درخواست‌های پیچیده (نیاز به همکاری چند عامل)
     */
    protected function handleComplexRequest(string $message, array $context = []): array
    {
        try {
            // 1. راهنما مسیر را مشخص می‌کند
            $roadmap = $this->guide->ask("نقشه راه برای: {$message}");
            
            // 2. مهندس طراحی می‌کند
            $design = $this->engineer->ask("طراحی فنی برای: {$message}\n\nنقشه راه:\n{$roadmap}");
            
            // 3. خلبان برنامه‌ریزی می‌کند
            $plan = $this->pilot->ask("برنامه‌ریزی اجرای: {$message}\n\nطراحی:\n{$design}");
            
            // 4. مهماندار گزارش کاربرپسند می‌دهد
            $report = $this->steward->ask("
یک گزارش کاربرپسند از موارد زیر بساز:

**درخواست:** {$message}

**نقشه راه:**
{$roadmap}

**طراحی فنی:**
{$design}

**برنامه اجرا:**
{$plan}

گزارش را به زبان ساده و قابل فهم برای کاربر عادی بنویس.
            ");
            
            return [
                'success' => true,
                'message' => $report,
                'agent' => 'team',
                'agent_persian_name' => 'تیم نجم‌هدا',
                'agent_icon' => '🌟',
                'details' => [
                    'roadmap' => $roadmap,
                    'design' => $design,
                    'plan' => $plan,
                ],
            ];
            
        } catch (\Exception $e) {
            Log::error('خطا در درخواست پیچیده: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'این درخواست پیچیده است. لطفاً ساده‌تر بیان کنید یا با پشتیبانی تماس بگیرید.',
                'agent' => 'system',
            ];
        }
    }
    
    /**
     * تشخیص نیت از روی پیام
     */
    protected function detectIntent(string $message, array $context = []): array
    {
        $message = mb_strtolower($message);
        
        // کلمات کلیدی برای هر نوع درخواست
        $engineeringKeywords = ['کد', 'برنامه', 'طراحی', 'معماری', 'دیتابیس', 'api', 'باگ', 'خطا', 'بهینه', 'امنیت', 'تست'];
        $managementKeywords = ['برنامه‌ریز', 'اسپرینت', 'پروژه', 'مدیریت', 'منابع', 'زمان‌بندی', 'گزارش', 'پیشرفت', 'کی پی آی'];
        $supportKeywords = ['چطور', 'چگونه', 'راهنما', 'کمک', 'آموزش', 'مشکل', 'نمی‌تونم', 'سوال', 'توضیح'];
        $guidanceKeywords = ['استراتژی', 'نقشه راه', 'هدف', 'چشم‌انداز', 'آینده', 'تصمیم', 'راهکار', 'پیشنهاد'];
        $architectKeywords = ['عامل جدید', 'ماژول جدید', 'قابلیت جدید', 'توسعه سیستم', 'اضافه کن', 'بساز برام', 'نیاز دارم به'];
        
        // شمارش تطابق‌ها
        $scores = [
            'engineering' => $this->calculateKeywordMatch($message, $engineeringKeywords),
            'management' => $this->calculateKeywordMatch($message, $managementKeywords),
            'support' => $this->calculateKeywordMatch($message, $supportKeywords),
            'guidance' => $this->calculateKeywordMatch($message, $guidanceKeywords),
            'architect' => $this->calculateKeywordMatch($message, $architectKeywords),
        ];
        
        // اگر از context کاربر ادمین بود، احتمال management/engineering بالاتر
        if (isset($context['user_is_admin']) && $context['user_is_admin']) {
            $scores['engineering'] *= 1.5;
            $scores['management'] *= 1.5;
        }
        
        // پیدا کردن بالاترین امتیاز
        arsort($scores);
        $topIntent = array_key_first($scores);
        $topScore = $scores[$topIntent];
        
        // اگر هیچ تطابقی نبود، support
        if ($topScore === 0) {
            return ['type' => 'support', 'confidence' => 0.5];
        }
        
        // اگر چند مورد امتیاز نزدیک داشتند، complex
        $secondScore = array_values($scores)[1] ?? 0;
        if ($topScore > 0 && $secondScore > 0 && ($topScore - $secondScore) < 2) {
            return ['type' => 'complex', 'confidence' => 0.6];
        }
        
        return [
            'type' => $topIntent,
            'confidence' => min($topScore / 10, 1.0),
        ];
    }
    
    /**
     * محاسبه تطابق کلمات کلیدی
     */
    protected function calculateKeywordMatch(string $text, array $keywords): int
    {
        $count = 0;
        foreach ($keywords as $keyword) {
            if (mb_strpos($text, $keyword) !== false) {
                $count++;
            }
        }
        return $count;
    }
    
    /**
     * تولید پیشنهادات مرتبط
     */
    protected function generateSuggestions(string $agent, string $message): array
    {
        $suggestions = [
            'engineer' => [
                'کد این قسمت رو بررسی کن',
                'یک Migration بساز برای...',
                'بهترین روش برای... چیه؟',
            ],
            'pilot' => [
                'وضعیت پروژه چطوره؟',
                'برنامه این هفته رو بده',
                'کارهای عقب‌افتاده رو نشون بده',
            ],
            'steward' => [
                'چطور ثبت‌نام کنم؟',
                'چطور کیف پول شارژ کنم؟',
                'مشکلم حل نشد، چیکار کنم؟',
            ],
            'guide' => [
                'نقشه راه 3 ماهه بساز',
                'اهداف سال آینده چی باشه؟',
                'این تصمیم درسته؟',
            ],
        ];
        
        return $suggestions[$agent] ?? [];
    }
    
    /**
     * دریافت یک عامل
     */
    protected function getAgent(string $name): ?BaseAgent
    {
        return match($name) {
            'engineer' => $this->engineer,
            'pilot' => $this->pilot,
            'steward' => $this->steward,
            'guide' => $this->guide,
            'architect' => $this->architect,
            default => null,
        };
    }
    
    /**
     * بررسی معتبر بودن نام عامل
     */
    protected function isValidAgent(string $name): bool
    {
        return in_array($name, ['engineer', 'pilot', 'steward', 'guide', 'architect']);
    }
    
    /**
     * بارگذاری اطلاعات پروژه
     */
    protected function loadProjectContext(): void
    {
        $this->projectContext = [
            'project_name' => 'NewEarthCoop (ارثکوپ)',
            'description' => 'پلتفرم تعاونی اقتصادی',
            'technology' => 'Laravel 11, Vue.js 3, MySQL',
            'phase' => $this->detectCurrentPhase(),
        ];
    }
    
    /**
     * تشخیص فاز فعلی پروژه
     */
    protected function detectCurrentPhase(): string
    {
        try {
            $userCount = \App\Models\User::count();
            
            if ($userCount === 0) {
                return 'راه‌اندازی اولیه';
            } elseif ($userCount < 10) {
                return 'توسعه';
            } elseif ($userCount < 100) {
                return 'آلفا';
            } elseif ($userCount < 1000) {
                return 'بتا';
            } else {
                return 'تولید';
            }
        } catch (\Exception $e) {
            return 'نامشخص';
        }
    }
    
    /**
     * دریافت آمار کلی سیستم
     */
    public function getSystemStats(): array
    {
        try {
            return [
                'agents_status' => [
                    'engineer' => $this->engineer->isEnabled(),
                    'pilot' => $this->pilot->isEnabled(),
                    'steward' => $this->steward->isEnabled(),
                    'guide' => $this->guide->isEnabled(),
                ],
                'project_phase' => $this->currentPhase,
                'total_interactions' => \App\Models\AIInteraction::count(),
                'total_conversations' => \App\Models\Conversation::count(),
            ];
        } catch (\Exception $e) {
            return ['error' => 'Unable to fetch stats'];
        }
    }
    
    /**
     * مدیریت پاسخ در حالت Mock
     */
    protected function handleMockResponse(string $agentName, string $message, array $context): array
    {
        $agent = $this->getAgent($agentName);
        $mockResponse = $this->mockService->getResponse(
            $agentName,
            json_encode($context),
            $message
        );
        
        return [
            'success' => true,
            'message' => $mockResponse,
            'agent' => $agentName,
            'agent_persian_name' => $agent ? $agent->getPersianName() : ucfirst($agentName),
            'agent_icon' => $agent ? $agent->getIcon() : '🤖',
            'suggestions' => $this->generateSuggestions($agentName, $message),
            'mock_mode' => true,
        ];
    }
    
    /**
     * دریافت پیام خوش‌آمدگویی
     */
    public function getWelcomeMessage(): string
    {
        $mockBadge = config('najm-hoda.mock_mode') ? "\n\n⚙️ **[Mock Mode فعال - تست بدون API]**" : "";
        
        return "سلام! من **نجم‌هدا** هستم 🌟

نرم‌افزار جامع مدیریت هوشمند دنیای ارثکوپ

من یک تیم 5 نفره هستم:
🔧 **مهندس**: طراحی، کدنویسی و بهینه‌سازی
✈️ **خلبان**: مدیریت پروژه و تصمیم‌گیری
👨‍✈️ **مهماندار**: پشتیبانی و آموزش
📖 **راهنما**: استراتژی و نقشه راه
🏗️ **معمار**: طراحی و ساخت عوامل جدید{$mockBadge}

چطور می‌تونم کمکتون کنم؟";
    }
}
