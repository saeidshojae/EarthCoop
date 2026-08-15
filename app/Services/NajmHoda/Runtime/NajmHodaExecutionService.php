<?php

namespace App\Services\NajmHoda\Runtime;

use App\Models\Conversation;
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
            $context = $this->sanitizeActionContext($context, $message);
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
     * Strip browser-forgeable execution controls unless trusted server code
     * supplies a real NajmHodaRuntimeActionAuthority object. Browser page hints
     * are resolved server-side, and persisted conversation text is separated
     * into role-preserving history instead of being promoted into system context.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function sanitizeActionContext(array $context, ?string $currentMessage = null): array
    {
        $authority = $context['runtime_action_authority'] ?? null;
        $browserPage = is_array($context['page'] ?? null) ? $context['page'] : [];
        $contextActorId = isset($context['user_id']) && is_numeric($context['user_id'])
            ? (int) $context['user_id']
            : null;
        $actorId = $authority instanceof NajmHodaRuntimeActionAuthority
            ? $authority->actorId
            : $contextActorId;
        $user = $actorId ? User::query()->find($actorId) : null;
        $pageContext = $this->pageContextResolver->resolve($user, ['page' => $browserPage]);
        [$conversationMeta, $conversationHistory] = $this->resolveConversationContext(
            $context['conversation'] ?? null,
            $actorId,
            $currentMessage
        );

        if (!$authority instanceof NajmHodaRuntimeActionAuthority) {
            $safe = [
                'page_context' => $pageContext,
                'user_id' => $actorId,
                'user_is_admin' => (bool) ($context['user_is_admin'] ?? false),
            ];

            if ($conversationMeta !== null) {
                $safe['conversation'] = $conversationMeta;
                $safe['conversation_history'] = $conversationHistory;
            }

            if (isset($context['force_agent']) && is_string($context['force_agent'])) {
                $safe['force_agent'] = $context['force_agent'];
            }

            return $safe;
        }

        unset($context['page'], $context['runtime_action_authority'], $context['conversation']);
        $context['page_context'] = $pageContext;
        $context['trusted_apply_request'] = $authority->allowApply;
        $context['runtime_authority_source'] = $authority->source;

        if ($conversationMeta !== null) {
            $context['conversation'] = $conversationMeta;
            $context['conversation_history'] = $conversationHistory;
        }

        if ($actorId !== null) {
            $context['user_id'] = $actorId;
        } else {
            unset($context['user_id']);
        }

        return $context;
    }

    /**
     * @return array{0: array<string, mixed>|null, 1: array<int, array{role:string,content:string}>}
     */
    protected function resolveConversationContext(mixed $value, ?int $actorId, ?string $currentMessage): array
    {
        if (!$value instanceof Conversation || !$actorId || (int) $value->user_id !== $actorId) {
            return [null, []];
        }

        $historyLimit = max(1, min(20, (int) config('najm-hoda.conversation_history_messages', 12)));
        $messages = $value->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderByDesc('id')
            ->limit($historyLimit + 1)
            ->get(['id', 'role', 'content'])
            ->reverse()
            ->values();

        $last = $messages->last();
        if (
            $last
            && (string) $last->role === 'user'
            && $currentMessage !== null
            && hash_equals(trim((string) $last->content), trim($currentMessage))
        ) {
            $messages->pop();
        }

        if ($messages->count() > $historyLimit) {
            $messages = $messages->slice(-$historyLimit)->values();
        }

        $history = $messages->map(function ($message): array {
            return [
                'role' => (string) $message->role,
                'content' => mb_substr((string) $message->content, 0, 2000),
            ];
        })->all();

        return [[
            'id' => (int) $value->id,
            'agent_type' => is_scalar($value->agent_type) ? mb_substr((string) $value->agent_type, 0, 30) : null,
            'status' => is_scalar($value->status) ? mb_substr((string) $value->status, 0, 30) : null,
        ], $history];
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
