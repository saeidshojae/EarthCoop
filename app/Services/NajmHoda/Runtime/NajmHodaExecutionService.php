<?php

namespace App\Services\NajmHoda\Runtime;

use App\Models\User;
use App\Services\NajmHoda\Context\NajmHodaPageContextResolver;
use App\Services\NajmHoda\NajmHodaInteractionBoundaryService;
use App\Services\NajmHoda\NajmHodaOrchestrator;
use Illuminate\Support\Str;
use Throwable;

class NajmHodaExecutionService
{
    public function __construct(
        protected NajmHodaInteractionBoundaryService $interactionBoundary,
        protected NajmHodaCrossModuleCapabilityOrchestratorService $actionOrchestrator,
        protected ?NajmHodaResourceAuthorizationService $resourceAuthorization = null,
        protected ?NajmHodaPageContextResolver $pageContextResolver = null
    ) {
        $this->resourceAuthorization = $this->resourceAuthorization ?? new NajmHodaResourceAuthorizationService();
        $this->pageContextResolver = $this->pageContextResolver ?? new NajmHodaPageContextResolver();
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
     * supplies a real NajmHodaRuntimeActionAuthority object. For ordinary chat,
     * browser context is reduced to an allow-listed page hint and then resolved
     * server-side before it can reach the model/orchestrator.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function sanitizeActionContext(array $context): array
    {
        $authority = $context['runtime_action_authority'] ?? null;
        $browserPage = is_array($context['page'] ?? null) ? $context['page'] : [];
        $actorId = isset($context['user_id']) && is_numeric($context['user_id'])
            ? (int) $context['user_id']
            : null;
        $user = $actorId ? User::query()->find($actorId) : null;
        $pageContext = $this->pageContextResolver->resolve($user, ['page' => $browserPage]);

        if (!$authority instanceof NajmHodaRuntimeActionAuthority) {
            $safe = [
                'page_context' => $pageContext,
                'user_id' => $actorId,
                'user_is_admin' => (bool) ($context['user_is_admin'] ?? false),
            ];

            if (isset($context['conversation'])) {
                $safe['conversation'] = $context['conversation'];
            }

            if (isset($context['force_agent']) && is_string($context['force_agent'])) {
                $safe['force_agent'] = $context['force_agent'];
            }

            return $safe;
        }

        unset($context['page']);
        $context['page_context'] = $pageContext;
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
     * They enter resource authorization and then the capability/safety/
     * delegation/executor runtime.
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

        $resourceCheck = $this->resourceAuthorization->authorize($actorId, $action, $input);
        if (!(bool) ($resourceCheck['allowed'] ?? false)) {
            return $this->actionResponse([
                'executed' => false,
                'status' => 'blocked',
                'reason' => (string) ($resourceCheck['reason'] ?? 'resource_authorization_denied'),
            ], $boundary, $requestId, $start);
        }

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
