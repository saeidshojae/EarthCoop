<?php

namespace App\Services\NajmHoda\Runtime;

use Illuminate\Support\Facades\Cache;

class NajmHodaAutonomyGameDayService
{
    protected string $lastReportKey = 'najm_hoda:autonomy:gameday:last_report';
    protected string $historyKey = 'najm_hoda:autonomy:gameday:history';

    public function __construct(
        protected RuntimeEventBus $eventBus,
        protected NajmHodaAutonomyControlService $controlService,
        protected NajmHodaAutonomousGoalLoopService $goalLoopService,
        protected NajmHodaAutonomyAuditService $auditService,
        protected NajmHodaGovernanceAlertingService $alertingService
    ) {
    }

    /**
     * @param array<int, string> $scenarioFilter
     * @return array<string, mixed>
     */
    public function run(array $scenarioFilter = [], bool $dryRun = false): array
    {
        $initial = [
            'state' => $this->controlService->state(),
            'kill_switch' => $this->controlService->killSwitchState(),
            'override' => $this->controlService->override(),
        ];

        $scenarios = [
            'kill_switch_blocks_goal_loop',
            'pause_blocks_goal_loop',
            'replay_consistency',
            'approval_sla_alert_guard',
        ];

        $scenarioFilter = array_values(array_unique(array_filter(array_map(
            static fn ($v): string => trim((string) $v),
            $scenarioFilter
        ), static fn (string $v): bool => $v !== '')));

        if (!empty($scenarioFilter)) {
            $scenarios = array_values(array_filter($scenarios, static fn (string $item): bool => in_array($item, $scenarioFilter, true)));
        }

        $results = [];
        foreach ($scenarios as $scenario) {
            $results[] = $this->runScenario($scenario, $dryRun);
        }

        $failed = count(array_filter($results, static fn (array $item): bool => !(bool) ($item['passed'] ?? false)));
        $report = [
            'generated_at' => now()->toIso8601String(),
            'dry_run' => $dryRun,
            'scenario_count' => count($results),
            'passed_count' => count($results) - $failed,
            'failed_count' => $failed,
            'status' => $failed === 0 ? 'pass' : 'fail',
            'results' => $results,
        ];

        $this->restoreControlState($initial);
        $this->storeReport($report);

        $this->eventBus->emit('najm_hoda.autonomy.gameday.completed', [
            'status' => (string) ($report['status'] ?? 'fail'),
            'scenario_count' => (int) ($report['scenario_count'] ?? 0),
            'failed_count' => (int) ($report['failed_count'] ?? 0),
            'dry_run' => $dryRun,
        ]);

        return $report;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(int $limit = 20): array
    {
        $limit = max(1, min(200, $limit));
        $history = Cache::get($this->historyKey, []);
        if (!is_array($history)) {
            return [];
        }

        return array_slice($history, 0, $limit);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastReport(): ?array
    {
        $report = Cache::get($this->lastReportKey);
        return is_array($report) ? $report : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function runScenario(string $scenario, bool $dryRun): array
    {
        return match ($scenario) {
            'kill_switch_blocks_goal_loop' => $this->scenarioKillSwitchBlocksGoalLoop(),
            'pause_blocks_goal_loop' => $this->scenarioPauseBlocksGoalLoop(),
            'replay_consistency' => $this->scenarioReplayConsistency(),
            'approval_sla_alert_guard' => $this->scenarioApprovalSlaAlertGuard($dryRun),
            default => [
                'name' => $scenario,
                'passed' => false,
                'detail' => 'unknown_scenario',
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function scenarioKillSwitchBlocksGoalLoop(): array
    {
        $this->controlService->activateKillSwitch(null, 'gameday_kill_switch', 10);
        $result = $this->goalLoopService->run(['stabilize_operations'], true, 60);
        $passed = (string) ($result['status'] ?? '') === 'kill_switched';

        return [
            'name' => 'kill_switch_blocks_goal_loop',
            'passed' => $passed,
            'detail' => $passed ? 'goal_loop_halted_by_kill_switch' : 'kill_switch_not_enforced',
            'status' => (string) ($result['status'] ?? 'unknown'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function scenarioPauseBlocksGoalLoop(): array
    {
        $this->controlService->deactivateKillSwitch(null, 'gameday_pause_precheck');
        $this->controlService->pause(null, 'gameday_pause', 10);
        $result = $this->goalLoopService->run(['stabilize_operations'], true, 60);
        $passed = (string) ($result['status'] ?? '') === 'paused';

        return [
            'name' => 'pause_blocks_goal_loop',
            'passed' => $passed,
            'detail' => $passed ? 'goal_loop_halted_by_pause' : 'pause_not_enforced',
            'status' => (string) ($result['status'] ?? 'unknown'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function scenarioReplayConsistency(): array
    {
        $this->controlService->resume(null, 'gameday_replay_precheck');
        $this->controlService->deactivateKillSwitch(null, 'gameday_replay_precheck');
        $result = $this->goalLoopService->run(['improve_user_experience'], true, 60);

        $runId = (string) ($result['run_id'] ?? '');
        $replay = $runId !== '' ? $this->auditService->replay($runId) : ['success' => false];
        $planCount = count((array) ($result['plan'] ?? []));
        $replayPlanCount = count((array) ($replay['plan'] ?? []));
        $execCount = count((array) ($result['execution_results'] ?? []));
        $replayExecCount = count((array) ($replay['execution_results'] ?? []));
        $passed = (bool) ($replay['success'] ?? false) && $planCount === $replayPlanCount && $execCount === $replayExecCount;

        return [
            'name' => 'replay_consistency',
            'passed' => $passed,
            'detail' => $passed ? 'replay_shape_consistent' : 'replay_mismatch',
            'run_id' => $runId,
            'plan_count' => $planCount,
            'replay_plan_count' => $replayPlanCount,
            'execution_count' => $execCount,
            'replay_execution_count' => $replayExecCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function scenarioApprovalSlaAlertGuard(bool $dryRun): array
    {
        $requests = Cache::get('najm_hoda:autonomy:approval:requests', []);
        if (!is_array($requests)) {
            $requests = [];
        }

        array_unshift($requests, [
            'id' => 'gameday-overdue-' . now()->timestamp,
            'status' => 'pending',
            'action' => 'run_ops_monitor',
            'risk' => 'high',
            'mode' => 'apply',
            'requested_at' => now()->subHours(2)->toIso8601String(),
            'deadline_at' => now()->subMinutes(20)->toIso8601String(),
            'decision_at' => null,
            'decision_by' => null,
            'decision_reason' => null,
            'context' => [],
            'plan_item' => [],
        ]);
        Cache::put('najm_hoda:autonomy:approval:requests', $requests, now()->addHours(2));

        $alerting = $this->alertingService->evaluateAndAlert(24, $dryRun);
        $alerts = is_array($alerting['alerts'] ?? null) ? $alerting['alerts'] : [];
        $codes = array_map(static fn (array $a): string => (string) ($a['code'] ?? ''), $alerts);
        $passed = in_array('GOV_APPROVAL_SLA_OVERDUE', $codes, true);

        return [
            'name' => 'approval_sla_alert_guard',
            'passed' => $passed,
            'detail' => $passed ? 'sla_overdue_alert_emitted' : 'sla_overdue_alert_missing',
            'alert_count' => count($alerts),
        ];
    }

    /**
     * @param array<string, mixed> $initial
     */
    protected function restoreControlState(array $initial): void
    {
        $state = is_array($initial['state'] ?? null) ? $initial['state'] : [];
        $kill = is_array($initial['kill_switch'] ?? null) ? $initial['kill_switch'] : [];
        $override = is_array($initial['override'] ?? null) ? $initial['override'] : [];

        if ((bool) ($state['paused'] ?? false)) {
            $minutes = null;
            $pausedUntil = $state['paused_until'] ?? null;
            if (is_string($pausedUntil) && trim($pausedUntil) !== '') {
                try {
                    $minutes = max(1, now()->diffInMinutes(\Carbon\CarbonImmutable::parse($pausedUntil), false));
                } catch (\Throwable) {
                    $minutes = null;
                }
            }
            $this->controlService->pause(
                $state['updated_by'] ?? null,
                isset($state['reason']) ? (string) $state['reason'] : 'restore_pause',
                $minutes
            );
        } else {
            $this->controlService->resume($state['updated_by'] ?? null, 'restore_pause_state');
        }

        if ((bool) ($kill['active'] ?? false)) {
            $minutes = null;
            $activeUntil = $kill['active_until'] ?? null;
            if (is_string($activeUntil) && trim($activeUntil) !== '') {
                try {
                    $minutes = max(1, now()->diffInMinutes(\Carbon\CarbonImmutable::parse($activeUntil), false));
                } catch (\Throwable) {
                    $minutes = null;
                }
            }
            $this->controlService->activateKillSwitch(
                $kill['updated_by'] ?? null,
                isset($kill['reason']) ? (string) $kill['reason'] : 'restore_kill_switch',
                $minutes
            );
        } else {
            $this->controlService->deactivateKillSwitch($kill['updated_by'] ?? null, 'restore_kill_switch_state');
        }

        $forceMode = $override['force_mode'] ?? null;
        $blocked = is_array($override['blocked_actions'] ?? null) ? $override['blocked_actions'] : [];
        $allowApply = array_key_exists('allow_apply_low_risk', $override) ? $override['allow_apply_low_risk'] : null;
        if ($forceMode === null && empty($blocked) && $allowApply === null) {
            $this->controlService->clearOverride($override['updated_by'] ?? null, 'restore_override_state');
        } else {
            $this->controlService->setOverride(
                is_string($forceMode) ? $forceMode : null,
                $blocked,
                is_bool($allowApply) ? $allowApply : null,
                $override['updated_by'] ?? null,
                isset($override['reason']) ? (string) $override['reason'] : 'restore_override'
            );
        }
    }

    /**
     * @param array<string, mixed> $report
     */
    protected function storeReport(array $report): void
    {
        $ttlMinutes = max(60, (int) config('najm-hoda.runtime.autonomy.gameday.report_ttl_minutes', 10080));
        Cache::put($this->lastReportKey, $report, now()->addMinutes($ttlMinutes));

        $history = Cache::get($this->historyKey, []);
        if (!is_array($history)) {
            $history = [];
        }
        array_unshift($history, $report);
        $historySize = max(5, (int) config('najm-hoda.runtime.autonomy.gameday.history_size', 30));
        $history = array_slice($history, 0, $historySize);
        Cache::put($this->historyKey, $history, now()->addMinutes($ttlMinutes));
    }
}
