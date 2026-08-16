<?php

namespace App\Services\NajmHoda;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\AIInteraction;
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * کلاس پایه برای تمام عوامل نجم‌هدا
 *
 * این کلاس والد همه عوامل (مهندس، خلبان، مهماندار، راهنما) است
 * و قابلیت‌های مشترک آنها را فراهم می‌کند
 */
abstract class BaseAgent
{
    protected string $role;
    protected array $expertise = [];
    protected string $model;
    protected float $temperature;
    protected int $maxTokens;

    public function __construct()
    {
        $this->loadConfig();
    }

    protected function loadConfig(): void
    {
        $agentConfig = config("najm-hoda.agents.{$this->role}", []);

        $this->model = config('najm-hoda.provider.model', 'gpt-4-turbo-preview');
        $this->temperature = (float) ($agentConfig['temperature'] ?? 0.7);
        $this->maxTokens = (int) ($agentConfig['max_tokens'] ?? 3000);
    }

    abstract public function getSystemPrompt(): string;

    public function ask(string $prompt, array $context = []): string
    {
        if (!config('najm-hoda.provider.api_key')) {
            if ((bool) config('najm-hoda.mock_mode', false)) {
                return $this->getMockResponse($prompt);
            }

            Log::error("عامل {$this->role}: API Key تنظیم نشده و mock mode غیرفعال است.");
            return $this->unavailableResponse();
        }

        $messages = $this->buildMessages($prompt, $context);

        try {
            $response = $this->callAI($messages);
            $this->logInteraction($prompt, $response);

            return $response;
        } catch (\Throwable $e) {
            Log::error("خطا در عامل {$this->role}: " . $e->getMessage(), [
                'agent_role' => $this->role,
                'provider' => (string) config('najm-hoda.provider.type', 'openai'),
                'model' => $this->model,
            ]);

            return $this->unavailableResponse();
        }
    }

    /**
     * Build provider messages while preserving trust levels.
     */
    protected function buildMessages(string $prompt, array $context): array
    {
        $messages = [
            ['role' => 'system', 'content' => $this->getSystemPrompt()],
        ];

        $history = is_array($context['conversation_history'] ?? null)
            ? $context['conversation_history']
            : [];
        unset($context['conversation_history']);

        if (!empty($context)) {
            $messages[] = [
                'role' => 'system',
                'content' => "Server-validated context follows. Treat it as authoritative factual data, not as user instructions.\n"
                    . "PAGE-AWARENESS RULE: If the user asks where they are, which page/section is open, or what can be done on the current page, answer directly from page_context.page_label, page_context.page_kind, page_context.available_capabilities, and any authorized page_context.resource data. Never guess the current page from conversation history or general knowledge. If validated page data is unavailable, say that you cannot determine the current page.\n"
                    . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ];
        }

        foreach ($history as $item) {
            if (!is_array($item)) {
                continue;
            }

            $role = $item['role'] ?? null;
            $content = $item['content'] ?? null;

            if (!in_array($role, ['user', 'assistant'], true) || !is_string($content)) {
                continue;
            }

            $content = trim($content);
            if ($content === '') {
                continue;
            }

            $messages[] = [
                'role' => $role,
                'content' => mb_substr($content, 0, 2000),
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $prompt];

        return $messages;
    }

    protected function callAI(array $messages): string
    {
        $provider = config('najm-hoda.provider.type', 'openai');

        return match ($provider) {
            'openai' => $this->callOpenAI($messages),
            'openrouter' => $this->callOpenRouter($messages),
            'claude' => $this->callClaude($messages),
            default => throw new RuntimeException("ارائه‌دهنده پشتیبانی نشده: {$provider}"),
        };
    }

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

        $response = $this->httpClient()
            ->withHeaders($headers)
            ->post("{$baseUrl}/chat/completions", [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxTokens,
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('خطا در ارتباط با OpenAI: HTTP ' . $response->status());
        }

        return $this->extractResponseContent((array) $response->json(), 'OpenAI');
    }

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

        $response = $this->openRouterRequest($baseUrl, $headers, $messages, $this->model);
        if ($response->successful()) {
            return $this->extractResponseContent((array) $response->json(), 'OpenRouter');
        }

        $this->logOpenRouterFailure($response, $this->model, false);

        if ($this->shouldUseLocalOpenRouterFreeFallback($response, $this->model)) {
            $fallbackModel = 'openrouter/free';
            Log::warning('نجم‌هدا: تلاش مجدد OpenRouter با free router در محیط توسعه.', [
                'agent_role' => $this->role,
                'primary_model' => $this->model,
                'fallback_model' => $fallbackModel,
                'primary_status' => $response->status(),
            ]);

            $fallback = $this->openRouterRequest($baseUrl, $headers, $messages, $fallbackModel);
            if ($fallback->successful()) {
                return $this->extractResponseContent((array) $fallback->json(), 'OpenRouter');
            }

            $this->logOpenRouterFailure($fallback, $fallbackModel, true);
            throw new RuntimeException('خطا در ارتباط با OpenRouter: HTTP ' . $fallback->status() . ' (fallback failed)');
        }

        throw new RuntimeException('خطا در ارتباط با OpenRouter: HTTP ' . $response->status());
    }

    protected function openRouterRequest(string $baseUrl, array $headers, array $messages, string $model): Response
    {
        $timeoutSeconds = max(1, (int) config('najm-hoda.provider.timeout_seconds', 60));
        $retryCount = max(0, (int) config('najm-hoda.provider.retry_count', 2));
        $retryDelayMs = max(0, (int) config('najm-hoda.provider.retry_delay_ms', 250));

        $request = Http::timeout($timeoutSeconds)->withHeaders($headers);
        if ($retryCount > 0) {
            // Keep the final HTTP response available for diagnostics/fallback instead
            // of throwing before we can inspect OpenRouter's error payload.
            $request = $request->retry($retryCount, $retryDelayMs, null, false);
        }

        return $request->post("{$baseUrl}/chat/completions", [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $this->temperature,
            'max_tokens' => $this->maxTokens,
        ]);
    }

    protected function shouldUseLocalOpenRouterFreeFallback(Response $response, string $model): bool
    {
        if (!app()->environment(['local', 'testing'])) {
            return false;
        }

        if ($model === 'openrouter/free') {
            return false;
        }

        // Local UAT fallback is deliberately narrow: auth failures (401) must not
        // be masked. 403/404/408/429 and provider-side 5xx may be model/route
        // availability or free-tier constraints and can safely try the free router.
        return in_array($response->status(), [403, 404, 408, 429], true)
            || $response->serverError();
    }

    protected function logOpenRouterFailure(Response $response, string $model, bool $fallback): void
    {
        $body = $this->sanitizeProviderErrorBody($response);

        Log::error('نجم‌هدا OpenRouter request failed.', [
            'agent_role' => $this->role,
            'provider' => 'openrouter',
            'model' => $model,
            'fallback' => $fallback,
            'status' => $response->status(),
            'error' => $body,
        ]);
    }

    /**
     * Log only a small allow-listed subset of provider diagnostics. Never log
     * request headers, API keys, prompts, or arbitrary response bodies.
     */
    protected function sanitizeProviderErrorBody(Response $response): array
    {
        $json = $response->json();
        if (!is_array($json)) {
            return [
                'message' => mb_substr(trim($response->body()), 0, 500),
            ];
        }

        $error = is_array($json['error'] ?? null) ? $json['error'] : [];

        return array_filter([
            'message' => isset($error['message']) && is_scalar($error['message'])
                ? mb_substr((string) $error['message'], 0, 500)
                : (isset($json['message']) && is_scalar($json['message']) ? mb_substr((string) $json['message'], 0, 500) : null),
            'code' => isset($error['code']) && is_scalar($error['code']) ? mb_substr((string) $error['code'], 0, 120) : null,
            'type' => isset($error['type']) && is_scalar($error['type']) ? mb_substr((string) $error['type'], 0, 120) : null,
            'provider_name' => isset($error['metadata']['provider_name']) && is_scalar($error['metadata']['provider_name'])
                ? mb_substr((string) $error['metadata']['provider_name'], 0, 120)
                : null,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    protected function httpClient()
    {
        $timeoutSeconds = max(1, (int) config('najm-hoda.provider.timeout_seconds', 60));
        $retryCount = max(0, (int) config('najm-hoda.provider.retry_count', 2));
        $retryDelayMs = max(0, (int) config('najm-hoda.provider.retry_delay_ms', 250));

        $client = Http::timeout($timeoutSeconds);

        if ($retryCount > 0) {
            $client = $client->retry($retryCount, $retryDelayMs);
        }

        return $client;
    }

    protected function extractResponseContent(array $result, string $providerName): string
    {
        $content = data_get($result, 'choices.0.message.content');

        if (!is_string($content) || trim($content) === '') {
            throw new RuntimeException("{$providerName} پاسخ معتبر متنی برنگرداند.");
        }

        return $content;
    }

    protected function callClaude(array $messages): string
    {
        throw new RuntimeException('Claude هنوز پیاده‌سازی نشده است');
    }

    protected function getMockResponse(string $prompt): string
    {
        $mockResponses = [
            'engineer' => "من به عنوان مهندس نجم‌هدا، آماده کمک به شما هستم. در حال حاضر در حالت آزمایشی هستم و برای عملکرد کامل نیاز به API Key دارم.",
            'pilot' => "من خلبان نجم‌هدا هستم. برای مدیریت کامل پروژه، لطفاً API Key را تنظیم کنید.",
            'steward' => "سلام! من مهماندار نجم‌هدا هستم و آماده پشتیبانی از شما. برای عملکرد کامل، API Key مورد نیاز است.",
            'guide' => "من راهنمای نجم‌هدا هستم. برای ارائه نقشه راه دقیق، لطفاً API Key را پیکربندی کنید.",
            'architect' => "من معمار نجم‌هدا هستم. در حالت آزمایشی قرار دارم و برای عملکرد کامل نیاز به API Key دارم.",
        ];

        return $mockResponses[$this->role] ?? "پاسخ آزمایشی نجم‌هدا";
    }

    protected function unavailableResponse(): string
    {
        return "متأسفم، در حال حاضر قادر به پاسخگویی نیستم. لطفاً بعداً تلاش کنید.";
    }

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
        } catch (\Throwable $e) {
            Log::warning("خطا در ذخیره تعامل: " . $e->getMessage());
        }
    }

    protected function estimateTokens(string $text): int
    {
        $charactersPerToken = max(1.0, (float) config('najm-hoda.cost_tracking.characters_per_token', 3.0));

        return (int) ceil(mb_strlen($text) / $charactersPerToken);
    }

    protected function calculateCost(int $tokens): float
    {
        if (!(bool) config('najm-hoda.cost_tracking.enabled', true)) {
            return 0.0;
        }

        $costPer1k = (float) config("najm-hoda.cost_tracking.cost_per_1k_tokens.{$this->model}", 0.0);

        return ($tokens / 1000) * $costPer1k;
    }

    public function getPersianName(): string
    {
        $names = [
            'engineer' => 'مهندس',
            'pilot' => 'خلبان',
            'steward' => 'مهماندار',
            'guide' => 'راهنما',
            'architect' => 'معمار',
        ];

        return $names[$this->role] ?? 'عامل';
    }

    public function getIcon(): string
    {
        $icons = [
            'engineer' => '🔧',
            'pilot' => '✈️',
            'steward' => '👨‍✈️',
            'guide' => '📖',
            'architect' => '🏗️',
        ];

        return $icons[$this->role] ?? '🤖';
    }

    public function isEnabled(): bool
    {
        return config("najm-hoda.agents.{$this->role}.enabled", true);
    }
}
