<?php

namespace App\Services\NajmHoda;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\AIInteraction;

/**
 * کلاس پایه برای تمام عوامل نجم‌هدا
 * 
 * این کلاس والد همه عوامل (مهندس، خلبان، مهماندار، راهنما) است
 * و قابلیت‌های مشترک آنها را فراهم می‌کند
 */
abstract class BaseAgent
{
    /**
     * نقش عامل (engineer, pilot, steward, guide)
     */
    protected string $role;
    
    /**
     * تخصص‌های عامل
     */
    protected array $expertise = [];
    
    /**
     * مدل AI مورد استفاده
     */
    protected string $model;
    
    /**
     * دمای تولید (0-1: کمتر = دقیق‌تر، بیشتر = خلاق‌تر)
     */
    protected float $temperature;
    
    /**
     * حداکثر تعداد توکن‌ها
     */
    protected int $maxTokens;
    
    public function __construct()
    {
        $this->loadConfig();
    }
    
    /**
     * بارگذاری تنظیمات از فایل config
     */
    protected function loadConfig(): void
    {
        $agentConfig = config("najm-hoda.agents.{$this->role}", []);
        
        $this->model = config('najm-hoda.provider.model', 'gpt-4-turbo-preview');
        $this->temperature = $agentConfig['temperature'] ?? 0.7;
        $this->maxTokens = $agentConfig['max_tokens'] ?? 3000;
    }
    
    /**
     * دریافت System Prompt برای هر عامل
     * 
     * هر عامل باید این متد را پیاده‌سازی کند
     */
    abstract public function getSystemPrompt(): string;
    
    /**
     * ارسال پیام به AI و دریافت پاسخ
     * 
     * @param string $prompt پیام کاربر
     * @param array $context اطلاعات اضافی
     * @return string پاسخ AI
     */
    public function ask(string $prompt, array $context = []): string
    {
        // اگر API Key وجود نداشت، پاسخ آزمایشی برگردان
        if (!config('najm-hoda.provider.api_key')) {
            return $this->getMockResponse($prompt);
        }
        
        $messages = $this->buildMessages($prompt, $context);
        
        try {
            $response = $this->callAI($messages);
            
            // لاگ کردن تعامل
            $this->logInteraction($prompt, $response);
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error("خطا در عامل {$this->role}: " . $e->getMessage());
            
            return "متأسفم، در حال حاضر قادر به پاسخگویی نیستم. لطفاً بعداً تلاش کنید.";
        }
    }
    
    /**
     * ساخت آرایه پیام‌ها برای ارسال به AI
     */
    protected function buildMessages(string $prompt, array $context): array
    {
        $messages = [
            ['role' => 'system', 'content' => $this->getSystemPrompt()]
        ];
        
        // اضافه کردن context در صورت وجود
        if (!empty($context)) {
            $messages[] = [
                'role' => 'system',
                'content' => 'اطلاعات اضافی: ' . json_encode($context, JSON_UNESCAPED_UNICODE)
            ];
        }
        
        $messages[] = ['role' => 'user', 'content' => $prompt];
        
        return $messages;
    }
    
    /**
     * فراخوانی API هوش مصنوعی
     */
    protected function callAI(array $messages): string
    {
        $provider = config('najm-hoda.provider.type', 'openai');
        
        switch ($provider) {
            case 'openai':
                return $this->callOpenAI($messages);
            case 'openrouter':
                return $this->callOpenRouter($messages);
            case 'claude':
                return $this->callClaude($messages);
            default:
                throw new \Exception("ارائه‌دهنده پشتیبانی نشده: {$provider}");
        }
    }
    
    /**
     * فراخوانی OpenAI API
     */
    protected function callOpenAI(array $messages): string
    {
        $baseUrl = rtrim((string) config('najm-hoda.provider.base_url', 'https://api.openai.com/v1'), '/');
        $headers = [
            'Authorization' => 'Bearer ' . config('najm-hoda.provider.api_key'),
            'Content-Type' => 'application/json',
        ];

        $organization = config('najm-hoda.provider.organization');
        if (!empty($organization)) {
            $headers['OpenAI-Organization'] = $organization;
        }

        $response = Http::timeout(60)
            ->withHeaders($headers)
            ->post("{$baseUrl}/chat/completions", [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxTokens,
            ]);
        
        if (!$response->successful()) {
            throw new \Exception('خطا در ارتباط با OpenAI: ' . $response->body());
        }
        
        $result = $response->json();
        
        return $result['choices'][0]['message']['content'] ?? '';
    }

    /**
     * فراخوانی OpenRouter API
     */
    protected function callOpenRouter(array $messages): string
    {
        $baseUrl = rtrim((string) config('najm-hoda.provider.openrouter.base_url', 'https://openrouter.ai/api/v1'), '/');
        $headers = [
            'Authorization' => 'Bearer ' . config('najm-hoda.provider.api_key'),
            'Content-Type' => 'application/json',
        ];

        $siteUrl = config('najm-hoda.provider.openrouter.site_url');
        if (!empty($siteUrl)) {
            $headers['HTTP-Referer'] = $siteUrl;
        }

        $appName = config('najm-hoda.provider.openrouter.app_name');
        if (!empty($appName)) {
            $headers['X-Title'] = $appName;
        }

        $response = Http::timeout(60)
            ->withHeaders($headers)
            ->post("{$baseUrl}/chat/completions", [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxTokens,
            ]);

        if (!$response->successful()) {
            throw new \Exception('خطا در ارتباط با OpenRouter: ' . $response->body());
        }

        $result = $response->json();

        return $result['choices'][0]['message']['content'] ?? '';
    }
    
    /**
     * فراخوانی Claude API
     */
    protected function callClaude(array $messages): string
    {
        // پیاده‌سازی برای Claude در آینده
        throw new \Exception('Claude هنوز پیاده‌سازی نشده است');
    }
    
    /**
     * دریافت پاسخ آزمایشی (زمانی که API Key وجود ندارد)
     */
    protected function getMockResponse(string $prompt): string
    {
        $mockResponses = [
            'engineer' => "من به عنوان مهندس نجم‌هدا، آماده کمک به شما هستم. در حال حاضر در حالت آزمایشی هستم و برای عملکرد کامل نیاز به API Key دارم.",
            'pilot' => "من خلبان نجم‌هدا هستم. برای مدیریت کامل پروژه، لطفاً API Key را تنظیم کنید.",
            'steward' => "سلام! من مهماندار نجم‌هدا هستم و آماده پشتیبانی از شما. برای عملکرد کامل، API Key مورد نیاز است.",
            'guide' => "من راهنمای نجم‌هدا هستم. برای ارائه نقشه راه دقیق، لطفاً API Key را پیکربندی کنید.",
        ];
        
        return $mockResponses[$this->role] ?? "پاسخ آزمایشی نجم‌هدا";
    }
    
    /**
     * ذخیره تعامل در دیتابیس
     */
    protected function logInteraction(string $input, string $output): void
    {
        try {
            $tokensUsed = $this->estimateTokens($input . $output);
            $cost = $this->calculateCost($tokensUsed);
            
            AIInteraction::create([
                'agent_role' => $this->role,
                'input' => $input,
                'output' => $output,
                'model' => $this->model,
                'tokens_used' => $tokensUsed,
                'cost' => $cost,
            ]);
        } catch (\Exception $e) {
            Log::warning("خطا در ذخیره تعامل: " . $e->getMessage());
        }
    }
    
    /**
     * تخمین تعداد توکن‌ها
     */
    protected function estimateTokens(string $text): int
    {
        // یک تخمین ساده: هر 4 کاراکتر ≈ 1 توکن
        return (int) ceil(mb_strlen($text) / 4);
    }
    
    /**
     * محاسبه هزینه بر اساس توکن‌ها
     */
    protected function calculateCost(int $tokens): float
    {
        $costPer1k = config("najm-hoda.cost_per_1k_tokens.{$this->model}", 0.01);
        
        return ($tokens / 1000) * $costPer1k;
    }
    
    /**
     * دریافت نام فارسی عامل
     */
    public function getPersianName(): string
    {
        $names = [
            'engineer' => 'مهندس',
            'pilot' => 'خلبان',
            'steward' => 'مهماندار',
            'guide' => 'راهنما',
        ];
        
        return $names[$this->role] ?? 'عامل';
    }
    
    /**
     * دریافت آیکون عامل
     */
    public function getIcon(): string
    {
        $icons = [
            'engineer' => '🔧',
            'pilot' => '✈️',
            'steward' => '👨‍✈️',
            'guide' => '📖',
        ];
        
        return $icons[$this->role] ?? '🤖';
    }
    
    /**
     * بررسی اینکه آیا عامل فعال است
     */
    public function isEnabled(): bool
    {
        return config("najm-hoda.agents.{$this->role}.enabled", true);
    }
}
