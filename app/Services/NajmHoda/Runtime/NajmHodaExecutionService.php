<?php

namespace App\Services\NajmHoda\Runtime;

use App\Services\NajmHoda\NajmHodaInteractionBoundaryService;
use App\Services\NajmHoda\NajmHodaOrchestrator;
use Illuminate\Support\Str;
use Throwable;

class NajmHodaExecutionService
{
    public function __construct(
        protected NajmHodaInteractionBoundaryService $interactionBoundary,
        protected NajmHodaCrossModuleCapabilityOrchestratorService $actionOrchestrator
    ) {
    }

    public function executeChat(NajmHodaOrchestrator $orchestrator, string $message, array $context = []): array
    {
        $requestId = (string) Str::uuid();
        $start = microtime(true);

        try {
            $context = $this->sanitizeActionContext($context);
            $boundary = $this->interactionBoundary->classify($message, $context);
            $mode = (string) ($boundary['mode'] ?? 'answer');

            if ($mode === 'blocked_action') {
                return $this->actionResponse([
                    'executed' => false,
                    'status' => 'blocked',
                    'reason' => (string) ($boundary['reason'] ?? 'action_blocked'),
                ], $boundary, $requestId, $start);
            }

            if ($mode === 'action') {
                return $this->executeAction($boundary, $context, $requestId, $start);
            }

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

    /**
     * Strip all browser-forgeable execution controls unless trusted server code
     * supplies a real NajmHodaRuntimeActionAuthority object.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function sanitizeActionContext(array $context): array
    {
        $authority = $context['runtime_action_authority'] ?? null;

        if (!$authority instanceof NajmHodaRuntimeActionAuthority) {
            foreach ([
                'requested_action',
                'capability_action',
                'action_input',
                'action_priority',
                'action_reason',
                'goals',
                'trusted_apply_request',
                'runtime_action_authority',
            ] as $key) {
                unset($context[$key]);
            }

            return $context;
        }

        // Ignore all browser-provided identity/apply claims. Runtime authority
        // is the single source of truth for the action actor and apply permission.
        $context['trusted_apply_request'] = $authority->allowApply;
        $context['runtime_authority_source'] = $authority->source;

        if ($authority->actorId !== null) {
            $context['user_id'] = $authority->actorId;
        } else {
            unset($context['user_id']);
        }

        return $context;
    }

    /**
     * Explicit actions never execute through the legacy chat orchestrator.
     * They enter the capability/safety/delegation/executor runtime instead.
     *
     * @param array<string, mixed> $boundary
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function executeAction(array $boundary, array $context, string $requestId, float $start): array
    {
        $action = (string) ($boundary['action'] ?? '');
        $input = is_array($boundary['input'] ?? null) ? $boundary['input'] : [];
        $goals = array_values(array_filter(array_map('strval', (array) ($context['goals'] ?? []))));
        $apply = (bool) ($context['trusted_apply_request'] ?? false);
        $actorId = isset($context['user_id']) ? (int) $context['user_id'] : null;

        $result = $this->actionOrchestrator->orchestrate([[
            'action' => $action,
            'priority' => (string) ($context['action_priority'] ?? 'stability'),
            'reason' => (string) ($context['action_reason'] ?? 'explicit_chat_action'),
            'input' => $input,
            'preconditions' => ['kill_switch_off'],
        ]], $goals, $apply, $actorId);

        return $this->actionResponse($result, $boundary, $requestId, $start);
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, mixed> $boundary
     * @return array<string, mixed>
     */
    protected function actionResponse(array $result, array $boundary, string $requestId, float $start): array
    {
        $status = (string) ($result['status'] ?? 'failed');
        $reason = (string) ($result['reason'] ?? data_get($result, 'steps.0.reason', ''));
        $stepStatus = (string) data_get($result, 'steps.0.status', '');
        $action = (string) ($boundary['action'] ?? '');
        $successful = (bool) ($result['executed'] ?? false) && $status === 'completed';

        if ($successful && $stepStatus === 'executed') {
            $message = 'درخواست با موفقیت از مسیر امن نجم هدا اجرا شد.';
        } elseif ($successful && $stepStatus === 'planned') {
            $message = 'درخواست بررسی و به‌صورت پیشنهاد ثبت شد؛ هنوز تغییری اعمال نشده است.';
        } else {
            $message = 'این درخواست اجرایی اعمال نشد و توسط کنترل‌های ایمنی نجم هدا متوقف شد.';
        }

        return [
            'success' => $successful,
            'message' => $message,
            'agent' => 'runtime',
            'agent_name' => 'نجم‌هدا',
            'agent_icon' => '🛡️',
            'suggestions' => [],
            'response_time_ms' => (int) round((microtime(true) - $start) * 1000),
            'request_id' => $requestId,
            'action' => $action,
            'action_status' => $stepStatus !== '' ? $stepStatus : $status,
            'action_reason' => $reason,
            'run_id' => (string) ($result['run_id'] ?? ''),
        ];
    }
}
