<?php

namespace App\Services\NajmHoda\Runtime;

use App\Services\NajmHoda\NajmHodaOrchestrator;
use Illuminate\Support\Str;
use Throwable;

class NajmHodaExecutionService
{
    public function executeChat(NajmHodaOrchestrator $orchestrator, string $message, array $context = []): array
    {
        $requestId = (string) Str::uuid();
        $start = microtime(true);

        try {
            $result = $orchestrator->route($message, $context);
            $durationMs = (int) round((microtime(true) - $start) * 1000);

            $success = (bool) ($result['success'] ?? false);
            if (!$success) {
                return [
                    'success' => false,
                    'message' => (string) ($result['message'] ?? 'متأسفانه مشکلی پیش آمد. لطفاً دوباره تلاش کنید.'),
                    'agent' => (string) ($result['agent'] ?? 'system'),
                    'suggestions' => (array) ($result['suggestions'] ?? []),
                    'response_time_ms' => $durationMs,
                    'request_id' => $requestId,
                    'error' => $result['error'] ?? null,
                ];
            }

            return [
                'success' => true,
                'message' => (string) ($result['message'] ?? ''),
                'agent' => (string) ($result['agent'] ?? 'unknown'),
                'agent_name' => (string) ($result['agent_persian_name'] ?? 'نجم‌هدا'),
                'agent_icon' => (string) ($result['agent_icon'] ?? '🤖'),
                'suggestions' => (array) ($result['suggestions'] ?? []),
                'response_time_ms' => $durationMs,
                'request_id' => $requestId,
            ];
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'message' => 'متأسفانه مشکلی پیش آمد. لطفاً دوباره تلاش کنید.',
                'agent' => 'system',
                'suggestions' => [],
                'response_time_ms' => (int) round((microtime(true) - $start) * 1000),
                'request_id' => $requestId,
                'error' => config('app.debug') ? $exception->getMessage() : null,
            ];
        }
    }
}

