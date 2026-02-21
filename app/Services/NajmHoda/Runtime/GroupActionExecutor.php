<?php

namespace App\Services\NajmHoda\Runtime;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class GroupActionExecutor
{
    public function execute(string $actionType, array $context, bool $dryRun, callable $operation): ?array
    {
        if ($rateLimited = $this->guardByRateLimit($actionType, $context)) {
            return $rateLimited;
        }

        if ($breaker = $this->guardByCircuitBreaker($actionType)) {
            return $breaker;
        }

        if ($dryRun) {
            return $this->normalizeResult([
                'decision' => 'proposed',
                'reason' => 'dry_run_enabled',
                'group_reply' => 'حالت اجرای آزمایشی فعال است. این اقدام فقط پیشنهاد شد و اجرا نشد.',
                'context' => array_merge(['action_type' => $actionType, 'dry_run' => true], $context),
            ], $actionType, true);
        }

        try {
            $result = $operation();
            if ($result === null) {
                return null;
            }
            if (!is_array($result)) {
                $failed = $this->normalizeResult([
                    'decision' => 'failed',
                    'reason' => 'executor_invalid_response',
                    'group_reply' => 'پاسخ اجرای اقدام معتبر نبود.',
                    'context' => ['action_type' => $actionType],
                ], $actionType, false);
                $this->recordFailure($actionType);
                return $failed;
            }

            $normalized = $this->normalizeResult($result, $actionType, false);
            if (($normalized['decision'] ?? '') === 'failed') {
                $this->recordFailure($actionType);
            } else {
                $this->resetFailures($actionType);
            }

            return $normalized;
        } catch (Throwable $exception) {
            Log::error('NajmHoda group action execution failed', [
                'action_type' => $actionType,
                'error' => $exception->getMessage(),
            ]);

            $failed = $this->normalizeResult([
                'decision' => 'failed',
                'reason' => 'executor_exception',
                'group_reply' => 'در اجرای اقدام خطایی رخ داد. لطفاً دوباره تلاش کنید.',
                'context' => ['action_type' => $actionType],
            ], $actionType, false);
            $this->recordFailure($actionType);
            return $failed;
        }
    }

    protected function normalizeResult(array $result, string $actionType, bool $dryRun): array
    {
        $normalized = $result;
        $normalized['decision'] = (string) ($result['decision'] ?? 'failed');
        $normalized['reason'] = (string) ($result['reason'] ?? 'executor_unknown');
        $normalized['group_reply'] = (string) ($result['group_reply'] ?? 'نتیجه اقدام نامشخص است.');

        $context = is_array($result['context'] ?? null) ? $result['context'] : [];
        $normalized['context'] = array_merge($context, [
            'action_type' => $actionType,
            'dry_run' => $dryRun,
        ]);

        return $normalized;
    }

    protected function guardByRateLimit(string $actionType, array $context): ?array
    {
        $globalMax = (int) config('najm-hoda.runtime.safety.rate_limit.max_actions_per_minute', 60);
        $actionMax = (int) config('najm-hoda.runtime.safety.rate_limit.max_actions_per_action_per_minute', 20);
        $globalMax = max(1, $globalMax);
        $actionMax = max(1, $actionMax);

        $groupId = (int) ($context['group_id'] ?? 0);
        $scope = $groupId > 0 ? "group:{$groupId}" : 'global';
        $bucket = (string) now()->format('YmdHi');

        $globalKey = "najm_hoda:executor:rate:{$scope}:all:{$bucket}";
        $actionKey = "najm_hoda:executor:rate:{$scope}:{$actionType}:{$bucket}";

        $globalCount = (int) Cache::get($globalKey, 0);
        $actionCount = (int) Cache::get($actionKey, 0);

        if ($globalCount >= $globalMax || $actionCount >= $actionMax) {
            return $this->normalizeResult([
                'decision' => 'skipped',
                'reason' => 'action_rate_limited',
                'group_reply' => 'تعداد اجرای اقدام در این بازه زمانی به سقف مجاز رسیده است.',
                'context' => [
                    'scope' => $scope,
                    'global_limit' => $globalMax,
                    'action_limit' => $actionMax,
                ],
            ], $actionType, false);
        }

        Cache::put($globalKey, $globalCount + 1, now()->addSeconds(70));
        Cache::put($actionKey, $actionCount + 1, now()->addSeconds(70));

        return null;
    }

    protected function guardByCircuitBreaker(string $actionType): ?array
    {
        $openUntil = Cache::get($this->circuitOpenKey($actionType));
        if (!is_numeric($openUntil)) {
            return null;
        }

        $remaining = ((int) $openUntil) - time();
        if ($remaining <= 0) {
            Cache::forget($this->circuitOpenKey($actionType));
            return null;
        }

        return $this->normalizeResult([
            'decision' => 'skipped',
            'reason' => 'circuit_breaker_open',
            'group_reply' => 'اجرای این اقدام موقتاً متوقف شده است. کمی بعد دوباره تلاش کنید.',
            'context' => ['retry_after_seconds' => $remaining],
        ], $actionType, false);
    }

    protected function recordFailure(string $actionType): void
    {
        $failureWindowSeconds = (int) config('najm-hoda.runtime.safety.circuit_breaker.failure_window_seconds', 600);
        $failureThreshold = (int) config('najm-hoda.runtime.safety.circuit_breaker.failure_threshold', 5);
        $cooldownSeconds = (int) config('najm-hoda.runtime.safety.circuit_breaker.cooldown_seconds', 300);

        $failureWindowSeconds = max(60, $failureWindowSeconds);
        $failureThreshold = max(1, $failureThreshold);
        $cooldownSeconds = max(30, $cooldownSeconds);

        $failKey = $this->failureCountKey($actionType);
        $count = (int) Cache::get($failKey, 0);
        $count++;
        Cache::put($failKey, $count, now()->addSeconds($failureWindowSeconds));

        if ($count >= $failureThreshold) {
            Cache::put($this->circuitOpenKey($actionType), time() + $cooldownSeconds, now()->addSeconds($cooldownSeconds));
        }
    }

    protected function resetFailures(string $actionType): void
    {
        Cache::forget($this->failureCountKey($actionType));
        Cache::forget($this->circuitOpenKey($actionType));
    }

    protected function failureCountKey(string $actionType): string
    {
        return "najm_hoda:executor:failures:{$actionType}";
    }

    protected function circuitOpenKey(string $actionType): string
    {
        return "najm_hoda:executor:circuit_open:{$actionType}";
    }
}
