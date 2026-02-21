<?php

namespace App\Services\NajmHoda\Runtime;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class NajmHodaAutonomousGoalLoopService
{
    public function __construct(
        protected RuntimeEventBus $eventBus,
        protected NajmHodaObservabilityGraphService $observabilityGraph,
        protected NajmHodaProactiveRecommendationService $recommendationService,
        protected NajmHodaOperatorActionExecutorV2 $actionExecutor,
        protected NajmHodaAutonomyControlService $controlService,
        protected NajmHodaAutonomyAuditService $auditService,
        protected NajmHodaCapabilityRegistry $capabilityRegistry,
        protected NajmHodaAutonomySafetyGate $safetyGate,
        protected NajmHodaAutonomyApprovalService $approvalService
    ) {
    }

    /**
     * @param array<int, string> $goals
     * @return array<string, mixed>
     */
    public function run(array $goals = [], bool $apply = false, ?int $contextLimit = null): array
    {
        if (!(bool) config('najm-hoda.enabled', true) || !(bool) config('najm-hoda.runtime.autonomy.enabled', false)) {
            return [
                'executed' => false,
                'status' => 'disabled',
                'reason' => 'autonomy_disabled',
            ];
        }
        if ($this->controlService->isPaused()) {
            $pausedResult = [
                'executed' => false,
                'status' => 'paused',
                'reason' => 'autonomy_paused_by_admin',
                'control_state' => $this->controlService->state(),
                'kill_switch' => $this->controlService->killSwitchState(),
            ];
            $this->auditService->record($pausedResult);
            return $pausedResult;
        }

        if ($this->controlService->isKillSwitchActive()) {
            $killSwitchResult = [
                'executed' => false,
                'status' => 'kill_switched',
                'reason' => 'global_kill_switch_active',
                'control_state' => $this->controlService->state(),
                'kill_switch' => $this->controlService->killSwitchState(),
            ];

            $this->eventBus->emit('najm_hoda.autonomy.goal_loop.kill_switched', [
                'reason' => 'global_kill_switch_active',
            ]);
            $this->auditService->record($killSwitchResult);
            return $killSwitchResult;
        }

        $contextLimit = $contextLimit ?? (int) config('najm-hoda.runtime.autonomy.context_limit', 200);
        $contextLimit = max(20, $contextLimit);

        $normalizedGoals = $this->normalizeGoals($goals);
        $context = $this->buildContextSummary($this->observabilityGraph->snapshot($contextLimit));
        $recommendations = $this->recommendationService->generate($normalizedGoals, $context);
        $rawPlan = $this->buildPlan($normalizedGoals, $context, $recommendations, $apply);
        $plan = $this->enforceSafety($rawPlan, $normalizedGoals);
        $plan = $this->applyControlOverrides($plan);

        $runId = (string) Str::uuid();
        $executionResults = $this->actionExecutor->execute($plan, $runId);

        $result = [
            'executed' => true,
            'status' => 'completed',
            'run_id' => $runId,
            'goals' => $normalizedGoals,
            'context' => $context,
            'recommendations' => $recommendations,
            'plan' => $plan,
            'execution_results' => $executionResults,
            'control_state' => $this->controlService->state(),
            'kill_switch' => $this->controlService->killSwitchState(),
            'control_override' => $this->controlService->override(),
            'apply_requested' => $apply,
            'generated_at' => now()->toIso8601String(),
        ];

        $ttlMinutes = max(30, (int) config('najm-hoda.runtime.autonomy.plan_ttl_minutes', 180));
        Cache::put('najm_hoda:autonomy:last_goal_plan', $result, now()->addMinutes($ttlMinutes));

        $this->eventBus->emit('najm_hoda.autonomy.goal_loop.executed', [
            'run_id' => $runId,
            'goal_count' => count($normalizedGoals),
            'top_priority' => data_get($plan, '0.priority'),
            'top_action' => data_get($plan, '0.action'),
            'executed_actions' => count(array_filter($executionResults, static fn (array $item): bool => (string) ($item['status'] ?? '') === 'executed')),
            'apply_requested' => $apply,
            'allow_apply_low_risk' => (bool) config('najm-hoda.runtime.autonomy.allow_apply_low_risk', false),
        ]);

        $this->auditService->record($result);

        return $result;
    }

    /**
     * @param array<int, string> $goals
     * @return array<int, string>
     */
    protected function normalizeGoals(array $goals): array
    {
        $fallback = config('najm-hoda.runtime.autonomy.default_goals', [
            'stabilize_operations',
            'improve_user_experience',
        ]);

        if (!is_array($fallback)) {
            $fallback = [];
        }

        $goals = empty($goals) ? $fallback : $goals;
        $maxGoals = max(1, (int) config('najm-hoda.runtime.autonomy.max_goals_per_run', 5));

        $normalized = [];
        foreach ($goals as $goal) {
            $value = trim((string) $goal);
            if ($value === '') {
                continue;
            }
            $normalized[] = $value;
        }

        $normalized = array_values(array_unique($normalized));
        $normalized = array_slice($normalized, 0, $maxGoals);

        if (empty($normalized)) {
            return ['stabilize_operations'];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    protected function buildContextSummary(array $snapshot): array
    {
        $runtime = (array) ($snapshot['runtime'] ?? []);

        return [
            'event_count' => (int) ($runtime['events_total'] ?? 0),
            'request_received' => (int) ($runtime['request_received'] ?? 0),
            'response_ready' => (int) ($runtime['response_ready'] ?? 0),
            'response_failed' => (int) ($runtime['response_failed'] ?? 0),
            'unresolved_requests' => (int) ($snapshot['unresolved_requests'] ?? 0),
            'error_rate_percent' => (float) ($snapshot['error_rate_percent'] ?? 0.0),
            'modules' => (array) ($snapshot['modules'] ?? []),
        ];
    }

    /**
     * @param array<int, string> $goals
     * @param array<string, mixed> $context
     * @param array<int, array<string, mixed>> $recommendations
     * @return array<int, array<string, mixed>>
     */
    protected function buildPlan(array $goals, array $context, array $recommendations, bool $apply): array
    {
        $warningErrorRate = (float) config('najm-hoda.runtime.autonomy.thresholds.warning_error_rate_percent', 15);
        $warningUnresolved = (int) config('najm-hoda.runtime.autonomy.thresholds.warning_unresolved_requests', 4);

        $errorRate = (float) ($context['error_rate_percent'] ?? 0.0);
        $unresolved = (int) ($context['unresolved_requests'] ?? 0);

        $priority = 'growth';
        $action = 'propose_engagement_recommendations';
        $reason = 'baseline';
        $input = [
            'goal_count' => count($goals),
            'health_status' => 'healthy',
            'error_rate_percent' => $errorRate,
            'unresolved_requests' => $unresolved,
            'recommendation_count' => count($recommendations),
            'top_recommendation_key' => (string) data_get($recommendations, '0.key', ''),
            'top_recommendation_confidence' => (float) data_get($recommendations, '0.confidence', 0),
        ];

        if ($errorRate >= $warningErrorRate || $unresolved >= $warningUnresolved) {
            $priority = 'stability';
            $action = 'run_ops_monitor';
            $reason = 'health_signals_above_warning';
            $input['health_status'] = 'warning';
        }

        $planned = $this->capabilityRegistry->makePlannedAction(
            $action,
            $input,
            $priority,
            $reason,
            $apply,
            $goals
        );

        if ($planned === null) {
            return [];
        }

        $planned['recommendations'] = $recommendations;

        return [$planned];
    }

    /**
     * @param array<int, array<string, mixed>> $plan
     * @param array<int, string> $goals
     * @return array<int, array<string, mixed>>
     */
    protected function enforceSafety(array $plan, array $goals): array
    {
        $accepted = [];

        foreach ($plan as $item) {
            if (!is_array($item)) {
                continue;
            }

            $gate = $this->safetyGate->evaluate($item, $goals, count($accepted));
            if (!(bool) ($gate['allowed'] ?? false)) {
                $this->eventBus->emit('najm_hoda.autonomy.plan_item.blocked', [
                    'action' => (string) ($item['action'] ?? ''),
                    'reason' => (string) ($gate['reason'] ?? 'safety_blocked'),
                ]);
                continue;
            }

            $accepted[] = $item;
        }

        return $this->enforceHumanEscalation($accepted, $goals);
    }

    /**
     * @param array<int, array<string, mixed>> $plan
     * @param array<int, string> $goals
     * @return array<int, array<string, mixed>>
     */
    protected function enforceHumanEscalation(array $plan, array $goals): array
    {
        $result = [];

        foreach ($plan as $item) {
            if (!is_array($item)) {
                continue;
            }

            if (!$this->shouldEscalateForHumanApproval($item)) {
                $result[] = $item;
                continue;
            }

            $approval = $this->approvalService->requestApproval($item, [
                'goals' => $goals,
                'source' => 'autonomous_goal_loop',
            ]);

            $item['requires_human_approval'] = true;
            $item['approval_request_id'] = (string) ($approval['id'] ?? '');
            $item['approval_status'] = 'pending';

            if ((bool) config('najm-hoda.runtime.autonomy.human_escalation.fallback_to_propose', true)) {
                $item['mode'] = 'propose';
                $item['fallback_applied'] = true;
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $item
     */
    protected function shouldEscalateForHumanApproval(array $item): bool
    {
        if (!(bool) config('najm-hoda.runtime.autonomy.human_escalation.enabled', true)) {
            return false;
        }

        $risk = (string) ($item['risk'] ?? 'low');
        $mode = (string) ($item['mode'] ?? 'propose');

        $riskLevels = config('najm-hoda.runtime.autonomy.human_escalation.require_approval_risk_levels', ['medium', 'high']);
        $riskLevels = is_array($riskLevels) ? array_map(static fn ($v): string => (string) $v, $riskLevels) : ['medium', 'high'];

        if (in_array($risk, $riskLevels, true)) {
            return true;
        }

        $requireApplyApproval = (bool) config('najm-hoda.runtime.autonomy.human_escalation.require_approval_for_apply_mode', true);
        if ($requireApplyApproval && $mode === 'apply') {
            return true;
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $plan
     * @return array<int, array<string, mixed>>
     */
    protected function applyControlOverrides(array $plan): array
    {
        $override = $this->controlService->override();
        $forceMode = $override['force_mode'] ?? null;
        $blockedActions = is_array($override['blocked_actions'] ?? null) ? $override['blocked_actions'] : [];
        $allowApply = $override['allow_apply_low_risk'] ?? null;

        $result = [];
        foreach ($plan as $item) {
            if (!is_array($item)) {
                continue;
            }

            $action = (string) ($item['action'] ?? '');
            if (in_array($action, $blockedActions, true)) {
                $this->eventBus->emit('najm_hoda.autonomy.plan_item.blocked', [
                    'action' => $action,
                    'reason' => 'admin_override_blocked_action',
                ]);
                continue;
            }

            if (is_string($forceMode) && in_array($forceMode, ['apply', 'propose'], true)) {
                $item['mode'] = $forceMode;
                $item['override_applied'] = true;
            }

            if ($allowApply === false && (string) ($item['mode'] ?? '') === 'apply') {
                $item['mode'] = 'propose';
                $item['override_applied'] = true;
            }

            $result[] = $item;
        }

        return $result;
    }
}
