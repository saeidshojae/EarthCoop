<?php

namespace App\Services\NajmHoda\Runtime;

use Illuminate\Support\Str;

class NajmHodaCrossModuleCapabilityOrchestratorService
{
    public function __construct(
        protected RuntimeEventBus $eventBus,
        protected NajmHodaCapabilityRegistry $capabilityRegistry,
        protected NajmHodaAutonomySafetyGate $safetyGate,
        protected NajmHodaOperatorActionExecutorV2 $actionExecutor,
        protected NajmHodaCompensatingTransactionService $compensationService,
        protected NajmHodaAutonomyControlService $controlService,
        protected NajmHodaMultiHorizonGoalEngineService $multiGoalEngine,
        protected NajmHodaMultiHorizonGoalReviewService $multiGoalReviewService
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $chain
     * @param array<int, string> $goals
     * @return array<string, mixed>
     */
    public function orchestrate(array $chain, array $goals, bool $apply): array
    {
        if (!(bool) config('najm-hoda.runtime.autonomy.orchestrator.enabled', true)) {
            return [
                'executed' => false,
                'status' => 'disabled',
                'reason' => 'orchestrator_disabled',
            ];
        }

        if ($this->controlService->isKillSwitchActive()) {
            return [
                'executed' => false,
                'status' => 'kill_switched',
                'reason' => 'global_kill_switch_active',
            ];
        }

        $runId = (string) Str::uuid();
        $stepResults = [];
        $executedSteps = [];

        foreach ($chain as $index => $step) {
            if (!is_array($step)) {
                continue;
            }

            $action = trim((string) ($step['action'] ?? ''));
            if ($action === '') {
                $stepResults[] = $this->stepFailure($index, $action, 'missing_action');
                $rollback = $this->rollback($runId, $executedSteps, $goals, $apply);
                return $this->failed($runId, $stepResults, $rollback, 'missing_action');
            }

            $precheck = $this->evaluatePreconditions((array) ($step['preconditions'] ?? []), $stepResults);
            if (!(bool) ($precheck['ok'] ?? false)) {
                $stepResults[] = $this->stepFailure($index, $action, (string) ($precheck['reason'] ?? 'precondition_failed'));
                $rollback = $this->rollback($runId, $executedSteps, $goals, $apply);
                return $this->failed($runId, $stepResults, $rollback, 'precondition_failed');
            }

            $priority = (string) ($step['priority'] ?? 'stability');
            $reason = (string) ($step['reason'] ?? 'cross_module_chain');
            $input = is_array($step['input'] ?? null) ? $step['input'] : [];
            $planned = $this->capabilityRegistry->makePlannedAction($action, $input, $priority, $reason, $apply, $goals);
            if ($planned === null) {
                $stepResults[] = $this->stepFailure($index, $action, 'contract_rejected');
                $rollback = $this->rollback($runId, $executedSteps, $goals, $apply);
                return $this->failed($runId, $stepResults, $rollback, 'contract_rejected');
            }

            $gate = $this->safetyGate->evaluate($planned, $goals, count($executedSteps));
            if (!(bool) ($gate['allowed'] ?? false)) {
                $stepResults[] = $this->stepFailure($index, $action, (string) ($gate['reason'] ?? 'safety_blocked'));
                $rollback = $this->rollback($runId, $executedSteps, $goals, $apply);
                return $this->failed($runId, $stepResults, $rollback, 'safety_blocked');
            }

            $result = (array) data_get($this->actionExecutor->execute([$planned], $runId), '0', []);
            $status = (string) ($result['status'] ?? 'unknown');
            $reason = (string) ($result['reason'] ?? '');
            if ($status === 'skipped' && $reason === 'mode_not_apply') {
                $stepResults[] = [
                    'step' => $index + 1,
                    'action' => $action,
                    'status' => 'planned',
                    'reason' => 'propose_mode',
                ];
                continue;
            }

            $stepResults[] = [
                'step' => $index + 1,
                'action' => $action,
                'status' => $status,
                'reason' => $reason,
            ];

            if ($status === 'executed') {
                $executedSteps[] = [
                    'action' => $action,
                    'rollback_action' => $this->rollbackAction($action),
                    'input' => $input,
                    'compensation' => is_array($step['compensation'] ?? null) ? $step['compensation'] : [],
                    'execution_context' => is_array($result['context'] ?? null) ? $result['context'] : [],
                ];
            } elseif ($status !== 'planned') {
                $rollback = $this->rollback($runId, $executedSteps, $goals, $apply);
                return $this->failed($runId, $stepResults, $rollback, 'step_not_executed');
            }

            $postcheck = $this->evaluatePostconditions(
                (array) ($step['postconditions'] ?? []),
                $runId,
                $action,
                $status
            );
            if (!(bool) ($postcheck['ok'] ?? false)) {
                $stepResults[] = $this->stepFailure($index, $action, (string) ($postcheck['reason'] ?? 'postcondition_failed'));
                $rollback = $this->rollback($runId, $executedSteps, $goals, $apply);
                return $this->failed($runId, $stepResults, $rollback, 'postcondition_failed');
            }
        }

        $this->eventBus->emit('najm_hoda.autonomy.orchestrator.chain.completed', [
            'run_id' => $runId,
            'step_count' => count($stepResults),
            'executed_count' => count($executedSteps),
        ]);

        return [
            'executed' => true,
            'status' => 'completed',
            'run_id' => $runId,
            'steps' => $stepResults,
            'rollback' => [],
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @param array<int, string> $goals
     * @return array<string, mixed>
     */
    public function orchestrateFromMultiGoals(array $query = [], array $goals = [], bool $apply = false): array
    {
        $review = $this->multiGoalReviewService->review($query);
        $snapshot = $this->multiGoalEngine->buildBacklog($query);
        $backlog = (array) ($snapshot['backlog'] ?? []);

        $maxSteps = max(1, (int) config('najm-hoda.runtime.autonomy.orchestrator.max_steps_per_chain', 3));
        $chain = [];

        foreach (array_slice($backlog, 0, $maxSteps) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $taskId = (string) ($item['id'] ?? '');
            $mapped = $this->mapBacklogTaskToAction($taskId, $item, (string) ($review['status'] ?? 'stable'));
            if ($mapped !== null) {
                $chain[] = $mapped;
            }
        }

        if (empty($chain)) {
            return [
                'executed' => false,
                'status' => 'no_action',
                'reason' => 'no_mappable_backlog_task',
                'review_status' => (string) ($review['status'] ?? 'stable'),
            ];
        }

        $result = $this->orchestrate($chain, $goals, $apply);
        $result['review_status'] = (string) ($review['status'] ?? 'stable');
        $result['source_backlog_count'] = count($backlog);

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $executedSteps
     * @param array<int, string> $goals
     * @return array<int, array<string, mixed>>
     */
    protected function rollback(string $runId, array $executedSteps, array $goals, bool $apply): array
    {
        $rollback = [];
        if (empty($executedSteps)) {
            return $rollback;
        }

        foreach (array_reverse($executedSteps) as $step) {
            $action = (string) ($step['action'] ?? '');
            $rollbackAction = (string) ($step['rollback_action'] ?? '');
            $entry = [
                'action' => $action,
                'rollback_action' => $rollbackAction,
                'status' => 'planned',
            ];
            $input = is_array($step['input'] ?? null) ? $step['input'] : [];
            $compensationResult = $this->compensationService->execute($step, $runId);
            $compHandled = (bool) ($compensationResult['handled'] ?? false);
            $compStatus = (string) ($compensationResult['status'] ?? 'skipped');

            if ($compHandled && $compStatus === 'executed') {
                $executed = [
                    'status' => 'executed',
                    'reason' => 'compensation_executed',
                    'compensation' => $compensationResult,
                ];
            } else {
                $fallbackEnabled = (bool) config('najm-hoda.runtime.autonomy.orchestrator.compensation.fallback_to_capability_rollback', true);
                if ($compHandled && !$fallbackEnabled) {
                    $executed = [
                        'status' => $compStatus,
                        'reason' => (string) ($compensationResult['reason'] ?? 'compensation_not_executed'),
                        'compensation' => $compensationResult,
                    ];
                } else {
                    $executed = $this->executeRollbackAction($runId, $action, $rollbackAction, $input, $goals, $apply);
                    $executed['compensation'] = $compensationResult;
                }
            }
            $rollback[] = array_merge($entry, $executed);

            $this->eventBus->emit('najm_hoda.autonomy.orchestrator.rollback.step', [
                'run_id' => $runId,
                'action' => $action,
                'rollback_action' => $rollbackAction,
            ]);
        }

        $this->eventBus->emit('najm_hoda.autonomy.orchestrator.rollback.completed', [
            'run_id' => $runId,
            'count' => count($rollback),
        ]);

        return $rollback;
    }

    /**
     * @param array<string, mixed> $originInput
     * @param array<int, string> $goals
     * @return array<string, mixed>
     */
    protected function executeRollbackAction(
        string $runId,
        string $originAction,
        string $rollbackAction,
        array $originInput,
        array $goals,
        bool $apply
    ): array {
        if ($rollbackAction === 'manual_review') {
            return [
                'status' => 'manual_required',
                'reason' => 'manual_review',
            ];
        }

        $rollbackInput = [
            'origin_action' => $originAction,
            'origin_run_id' => $runId,
            'origin_input' => $originInput,
        ];
        $planned = $this->capabilityRegistry->makePlannedAction(
            $rollbackAction,
            $rollbackInput,
            'stability',
            'orchestrator_rollback',
            $apply,
            $goals
        );

        if ($planned === null) {
            return [
                'status' => 'failed',
                'reason' => 'rollback_contract_rejected',
            ];
        }

        $gate = $this->safetyGate->evaluate($planned, $goals, 0);
        if (!(bool) ($gate['allowed'] ?? false)) {
            return [
                'status' => 'failed',
                'reason' => (string) ($gate['reason'] ?? 'rollback_safety_blocked'),
            ];
        }

        $result = (array) data_get($this->actionExecutor->execute([$planned], $runId), '0', []);
        return [
            'status' => (string) ($result['status'] ?? 'unknown'),
            'reason' => (string) ($result['reason'] ?? ''),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $steps
     * @param array<int, array<string, mixed>> $rollback
     * @return array<string, mixed>
     */
    protected function failed(string $runId, array $steps, array $rollback, string $reason): array
    {
        $this->eventBus->emit('najm_hoda.autonomy.orchestrator.chain.failed', [
            'run_id' => $runId,
            'reason' => $reason,
            'executed_steps' => count(array_filter($steps, static fn (array $s): bool => (string) ($s['status'] ?? '') === 'executed')),
            'rollback_count' => count($rollback),
        ]);

        return [
            'executed' => false,
            'status' => 'failed',
            'reason' => $reason,
            'run_id' => $runId,
            'steps' => $steps,
            'rollback' => $rollback,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $stepResults
     * @return array{ok:bool,reason?:string}
     */
    protected function evaluatePreconditions(array $preconditions, array $stepResults): array
    {
        foreach ($preconditions as $condition) {
            $condition = strtolower(trim((string) $condition));
            if ($condition === '' || $condition === 'always') {
                continue;
            }

            if ($condition === 'no_previous_failures') {
                foreach ($stepResults as $result) {
                    $status = (string) ($result['status'] ?? '');
                    if (!in_array($status, ['executed', 'planned'], true)) {
                        return ['ok' => false, 'reason' => 'previous_step_not_executed'];
                    }
                }
                continue;
            }

            if ($condition === 'kill_switch_off' && $this->controlService->isKillSwitchActive()) {
                return ['ok' => false, 'reason' => 'kill_switch_active'];
            }
        }

        return ['ok' => true];
    }

    /**
     * @param array<int, mixed> $postconditions
     * @return array{ok:bool,reason?:string}
     */
    protected function evaluatePostconditions(array $postconditions, string $runId, string $action, string $status): array
    {
        if ($status === 'planned') {
            return ['ok' => true];
        }

        if ($status !== 'executed') {
            return ['ok' => false, 'reason' => 'step_not_executed'];
        }

        if (empty($postconditions)) {
            return ['ok' => true];
        }

        foreach ($postconditions as $condition) {
            $condition = strtolower(trim((string) $condition));
            if ($condition === '' || $condition === 'always') {
                continue;
            }

            if ($condition === 'executor_intent_recorded') {
                $events = $this->eventBus->recent('najm_hoda.autonomy.executor.intent', 50);
                $found = false;
                foreach ($events as $event) {
                    $payload = is_array($event['payload'] ?? null) ? $event['payload'] : [];
                    if (
                        (string) ($payload['run_id'] ?? '') === $runId
                        && (string) ($payload['action'] ?? '') === $action
                    ) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    return ['ok' => false, 'reason' => 'missing_executor_intent_signal'];
                }
            }
        }

        return ['ok' => true];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>|null
     */
    protected function mapBacklogTaskToAction(string $taskId, array $item, string $reviewStatus): ?array
    {
        $priority = (string) ($item['priority'] ?? 'stability');
        $trigger = (string) ($item['trigger'] ?? 'multi_goal_backlog');

        if (
            str_starts_with($taskId, 'recover_critical_coverage')
            || str_starts_with($taskId, 'open_ops_incident_')
            || str_starts_with($taskId, 'normalize_runtime_risk_labels')
        ) {
            return [
                'action' => 'run_ops_monitor',
                'priority' => $priority,
                'reason' => $trigger,
                'input' => [
                    'health_status' => $reviewStatus === 'regressing' ? 'warning' : 'healthy',
                    'task_id' => $taskId,
                ],
                'preconditions' => ['kill_switch_off', 'no_previous_failures'],
                'postconditions' => ['executor_intent_recorded'],
            ];
        }

        if (str_starts_with($taskId, 'escalate_support_ticket_')) {
            $ticketId = (int) preg_replace('/\D+/', '', $taskId);
            if ($ticketId <= 0) {
                return null;
            }

            return [
                'action' => 'set_ticket_needs_review',
                'priority' => $priority,
                'reason' => $trigger,
                'input' => [
                    'ticket_id' => $ticketId,
                    'target_status' => 'needs_review',
                ],
                'preconditions' => ['kill_switch_off', 'no_previous_failures'],
                'postconditions' => ['executor_intent_recorded'],
                'compensation' => [
                    'type' => 'ticket_status_revert',
                    'ticket_id' => $ticketId,
                    'use_execution_context_previous_status' => true,
                ],
            ];
        }

        if (str_starts_with($taskId, 'review_project_delivery_')) {
            return [
                'action' => 'propose_engagement_recommendations',
                'priority' => $priority,
                'reason' => $trigger,
                'input' => [
                    'goal_count' => 1,
                    'health_status' => 'warning',
                    'recommendation_count' => 1,
                    'top_recommendation_key' => 'project_delivery_followup',
                    'top_recommendation_confidence' => 0.7,
                ],
                'preconditions' => ['kill_switch_off', 'no_previous_failures'],
                'postconditions' => ['executor_intent_recorded'],
            ];
        }

        return null;
    }

    protected function rollbackAction(string $action): string
    {
        $configured = config("najm-hoda.runtime.autonomy.orchestrator.rollback_map.{$action}");
        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        return 'manual_review';
    }

    /**
     * @return array<string, mixed>
     */
    protected function stepFailure(int $index, string $action, string $reason): array
    {
        return [
            'step' => $index + 1,
            'action' => $action,
            'status' => 'failed',
            'reason' => $reason,
        ];
    }
}
