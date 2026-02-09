<?php

namespace App\Services\NajmHoda\CodeScanner;

use App\Services\NajmHoda\BaseAgent;

/**
 * سرویس تحلیل کد با هوش مصنوعی
 */
class CodeAnalyzerService extends BaseAgent
{
    protected string $role = 'code_analyzer';
    protected string $persianName = 'تحلیلگر کد';

    protected array $expertise = [
        'تحلیل عمیق کد',
        'تشخیص مشکلات پیچیده',
        'پیشنهاد راه‌حل‌های بهینه',
        'توضیح مشکلات',
    ];

    /**
     * دریافت System Prompt
     */
    public function getSystemPrompt(): string
    {
        return "تو یک تحلیلگر حرفه‌ای کد Laravel هستی با تخصص در:\n" .
               "- تشخیص مشکلات امنیتی و Performance\n" .
               "- بهینه‌سازی کد و رفع باگ‌ها\n" .
               "- پیشنهاد راه‌حل‌های عملی و کاربردی\n\n" .
               "همیشه پاسخ‌های دقیق، کاربردی و قابل اجرا بده.";
    }

    /**
     * تحلیل یک issue خاص
     */
    public function analyzeIssue(array $issue, string $fileContent): array
    {
        $prompt = $this->buildAnalysisPrompt($issue, $fileContent);
        
        try {
            $response = $this->ask($prompt);
            
            return $this->parseAnalysisResponse($response, $issue);
            
        } catch (\Exception $e) {
            \Log::error('خطا در تحلیل کد: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * ساخت prompt برای تحلیل
     */
    protected function buildAnalysisPrompt(array $issue, string $fileContent): string
    {
        $context = "نوع مشکل: {$issue['type']}\n";
        $context .= "سطح اهمیت: {$issue['severity']}\n";
        $context .= "خط: {$issue['line']}\n";
        $context .= "کد مشکل‌دار:\n```php\n{$issue['code']}\n```\n\n";
        
        // اضافه کردن context اطراف کد
        $contextLines = $this->extractContextLines($fileContent, $issue['line']);
        $context .= "کد اطراف:\n```php\n{$contextLines}\n```\n\n";

        $prompt = "تو یک تحلیلگر حرفه‌ای کد Laravel هستی.\n\n";
        $prompt .= $context;
        $prompt .= "لطفاً:\n";
        $prompt .= "1. مشکل را به طور کامل توضیح بده\n";
        $prompt .= "2. چرا این مشکل مهم است؟\n";
        $prompt .= "3. چه عواقبی می‌تواند داشته باشد؟\n";
        $prompt .= "4. بهترین راه‌حل چیست؟\n\n";
        $prompt .= "پاسخ را به صورت JSON با فرمت زیر بده:\n";
        $prompt .= "{\n";
        $prompt .= '  "explanation": "توضیح کامل مشکل",' . "\n";
        $prompt .= '  "consequences": ["عاقبت 1", "عاقبت 2"],' . "\n";
        $prompt .= '  "solution": "راه‌حل پیشنهادی",' . "\n";
        $prompt .= '  "priority": "critical|high|medium|low"' . "\n";
        $prompt .= "}";

        return $prompt;
    }

    /**
     * استخراج خطوط اطراف کد
     */
    protected function extractContextLines(string $fileContent, int $lineNumber, int $contextSize = 5): string
    {
        $lines = explode("\n", $fileContent);
        $start = max(0, $lineNumber - $contextSize - 1);
        $end = min(count($lines), $lineNumber + $contextSize);
        
        $contextLines = array_slice($lines, $start, $end - $start);
        
        $result = [];
        for ($i = 0; $i < count($contextLines); $i++) {
            $currentLine = $start + $i + 1;
            $marker = ($currentLine === $lineNumber) ? '>>> ' : '    ';
            $result[] = $marker . str_pad($currentLine, 4, ' ', STR_PAD_LEFT) . ' | ' . $contextLines[$i];
        }
        
        return implode("\n", $result);
    }

    /**
     * پردازش پاسخ تحلیل
     */
    protected function parseAnalysisResponse(string $response, array $issue): array
    {
        // تلاش برای استخراج JSON از پاسخ
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $jsonData = json_decode($matches[0], true);
            
            if ($jsonData) {
                return [
                    'success' => true,
                    'analysis' => [
                        'explanation' => $jsonData['explanation'] ?? '',
                        'consequences' => $jsonData['consequences'] ?? [],
                        'solution' => $jsonData['solution'] ?? '',
                        'priority' => $jsonData['priority'] ?? $issue['severity'],
                    ],
                    'raw_response' => $response,
                ];
            }
        }

        // اگر JSON نبود، پاسخ متنی برمی‌گردونیم
        return [
            'success' => true,
            'analysis' => [
                'explanation' => $response,
                'consequences' => [],
                'solution' => '',
                'priority' => $issue['severity'],
            ],
            'raw_response' => $response,
        ];
    }

    /**
     * تولید پیشنهاد کد برای رفع مشکل
     */
    public function generateCodeSuggestion(array $issue, string $fileContent): array
    {
        $prompt = $this->buildSuggestionPrompt($issue, $fileContent);
        
        try {
            $response = $this->ask($prompt);
            
            return $this->parseSuggestionResponse($response);
            
        } catch (\Exception $e) {
            \Log::error('خطا در تولید پیشنهاد: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * ساخت prompt برای تولید پیشنهاد
     */
    protected function buildSuggestionPrompt(array $issue, string $fileContent): string
    {
        $context = "نوع مشکل: {$issue['type']}\n";
        $context .= "خط: {$issue['line']}\n";
        
        // استخراج متد یا کلاس کامل
        $relevantCode = $this->extractRelevantCode($fileContent, $issue['line']);
        $context .= "کد فعلی:\n```php\n{$relevantCode}\n```\n\n";

        $prompt = "تو یک برنامه‌نویس حرفه‌ای Laravel هستی.\n\n";
        $prompt .= $context;
        $prompt .= "مشکل: {$issue['message']}\n\n";
        $prompt .= "لطفاً کد بهینه شده را بنویس که این مشکل را حل کند.\n";
        $prompt .= "فقط کد را بنویس، بدون توضیح اضافه.\n";
        $prompt .= "پاسخ را به صورت JSON با فرمت زیر بده:\n";
        $prompt .= "{\n";
        $prompt .= '  "original_code": "کد فعلی",' . "\n";
        $prompt .= '  "suggested_code": "کد پیشنهادی",' . "\n";
        $prompt .= '  "explanation": "توضیح کوتاه تغییرات"' . "\n";
        $prompt .= "}";

        return $prompt;
    }

    /**
     * استخراج کد مرتبط (متد یا کلاس)
     */
    protected function extractRelevantCode(string $fileContent, int $lineNumber): string
    {
        $lines = explode("\n", $fileContent);
        
        // پیدا کردن شروع متد یا کلاس
        $start = $lineNumber - 1;
        while ($start > 0 && !preg_match('/^\s*(public|protected|private|function|class)/', $lines[$start])) {
            $start--;
        }
        
        // پیدا کردن پایان متد یا کلاس
        $end = $lineNumber - 1;
        $braceCount = 0;
        $foundStart = false;
        
        for ($i = $start; $i < count($lines); $i++) {
            $line = $lines[$i];
            $braceCount += substr_count($line, '{') - substr_count($line, '}');
            
            if (substr_count($line, '{') > 0) {
                $foundStart = true;
            }
            
            if ($foundStart && $braceCount === 0) {
                $end = $i;
                break;
            }
        }
        
        return implode("\n", array_slice($lines, $start, $end - $start + 1));
    }

    /**
     * پردازش پاسخ پیشنهاد
     */
    protected function parseSuggestionResponse(string $response): array
    {
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $jsonData = json_decode($matches[0], true);
            
            if ($jsonData) {
                return [
                    'success' => true,
                    'original_code' => $jsonData['original_code'] ?? '',
                    'suggested_code' => $jsonData['suggested_code'] ?? '',
                    'explanation' => $jsonData['explanation'] ?? '',
                    'raw_response' => $response,
                ];
            }
        }

        return [
            'success' => false,
            'error' => 'نتوانستم پیشنهاد مناسب تولید کنم',
            'raw_response' => $response,
        ];
    }

    /**
     * بررسی چند issue یکجا
     */
    public function analyzeMultipleIssues(array $issues, string $filePath): array
    {
        if (!file_exists($filePath)) {
            return ['success' => false, 'error' => 'فایل یافت نشد'];
        }

        $fileContent = file_get_contents($filePath);
        $results = [];

        foreach ($issues as $issue) {
            $analysis = $this->analyzeIssue($issue, $fileContent);
            
            if ($analysis['success']) {
                $suggestion = $this->generateCodeSuggestion($issue, $fileContent);
                
                $results[] = [
                    'issue' => $issue,
                    'analysis' => $analysis['analysis'],
                    'suggestion' => $suggestion,
                ];
            }
        }

        return [
            'success' => true,
            'file' => $filePath,
            'total_issues' => count($issues),
            'results' => $results,
        ];
    }
}
