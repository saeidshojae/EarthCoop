<?php

namespace App\Services\NajmHoda\Runtime;

use Illuminate\Support\Facades\Cache;

class NajmHodaOperationalAutonomyActivationService
{
    protected string $stateKey = 'najm_hoda:autonomy:operations_24x7:state';
    protected string $historyKey = 'najm_hoda:autonomy:operations_24x7:history';

    public function __construct(
        protected RuntimeEventBus $eventBus,
        protected NajmHodaContinuousEvaluationHarnessService $evaluationService,
        protected NajmHodaSafeCodeOpsCanaryService $codeOpsCanaryService,
        protected NajmHodaGovernanceAlertingService $alertingService,
        protected NajmHodaAutonomyControlService $controlService,
        protected NajmHodaRunbookRegistryService $runbookService
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function activate(?int $byUserId = null, ?string $mode = null, ?string $reason = null): array
    {
        if (!(bool) config('najm-hoda.runtime.autonomy.operations_24x7.enabled', true)) {
            return [
                'success' => false,
                'reason' => 'operations_24x7_disabled',
            ];
        }

        $mode = $this->normalizeMode($mode);
        $state = $this->status();
        $state['status'] = 'active';
        $state['mode'] = $mode;
        $state['activated_at'] = now()->toIso8601String();
        $state['activated_by'] = $byUserId;
        $state['reason'] = $reason;
        $state['last_tick_at'] = null;
        $state['last_tick_status'] = null;
        $state['consecutive_breach_count'] = 0;
        $state['updated_at'] = now()->toIso8601String();
        $this->storeState($state);

        $this->appendHistory([
            'type' => 'activated',
            'timestamp' => now()->toIso8601String(),
            'by_user_id' => $byUserId,
            'mode' => $mode,
            'reason' => $reason,
        ]);
        $this->eventBus->emit('najm_hoda.autonomy.operations_24x7.activated', [
            'mode' => $mode,
            'by_user_id' => $byUserId,
            'reason' => $reason,
        ]);

        return [
            'success' => true,
            'state' => $state,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function deactivate(?int $byUserId = null, ?string $reason = null): array
    {
        $state = $this->status();
        $state['status'] = 'inactive';
        $state['updated_at'] = now()->toIso8601String();
        $state['deactivated_at'] = now()->toIso8601String();
        $state['deactivated_by'] = $byUserId;
        $state['deactivation_reason'] = $reason;
        $this->storeState($state);

        $this->appendHistory([
            'type' => 'deactivated',
            'timestamp' => now()->toIso8601String(),
            'by_user_id' => $byUserId,
            'reason' => $reason,
        ]);
        $this->eventBus->emit('najm_hoda.autonomy.operations_24x7.deactivated', [
            'by_user_id' => $byUserId,
            'reason' => $reason,
        ]);

        return [
            'success' => true,
            'state' => $state,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function tick(?int $byUserId = null, bool $manual = false, ?int $windowHours = null): array
    {
        if (!(bool) config('najm-hoda.runtime.autonomy.operations_24x7.enabled', true)) {
            return ['success' => false, 'reason' => 'operations_24x7_disabled'];
        }

        $state = $this->status();
        if ((string) ($state['status'] ?? 'inactive') !== 'active') {
            return [
                'success' => true,
                'active' => false,
                'status' => (string) ($state['status'] ?? 'inactive'),
                'reason' => 'operations_24x7_inactive',
                'state' => $state,
            ];
        }

        $inShift = $manual ? true : $this->isInVirtualShift((string) ($state['mode'] ?? 'night_only'));
        if (!$inShift) {
            $state['last_tick_at'] = now()->toIso8601String();
            $state['last_tick_status'] = 'out_of_shift';
            $state['updated_at'] = now()->toIso8601String();
            $this->storeState($state);

            return [
                'success' => true,
                'active' => true,
                'status' => 'out_of_shift',
                'state' => $state,
            ];
        }

        $windowHours = max(1, min(720, $windowHours ?? (int) config('najm-hoda.runtime.autonomy.operations_24x7.window_hours', 24)));
        $evaluation = $this->evaluationService->run($windowHours, false);
        $canary = $this->codeOpsCanaryService->evaluate(true, $byUserId, 'ops_24x7_tick');
        $alerts = $this->alertingService->evaluateAndAlert($windowHours, false);

        $criticalAlerts = count(array_filter((array) ($alerts['alerts'] ?? []), static function (array $row): bool {
            return (string) ($row['severity'] ?? '') === 'critical';
        }));
        $evaluationStatus = (string) ($evaluation['status'] ?? 'ok');
        $tickStatus = 'ok';
        if ($evaluationStatus === 'breach' || $criticalAlerts >= max(1, (int) config('najm-hoda.runtime.autonomy.operations_24x7.stop.critical_alert_threshold', 1))) {
            $tickStatus = 'breach';
        } elseif ($evaluationStatus === 'warning' || ((int) ($alerts['count'] ?? 0) > 0)) {
            $tickStatus = 'warning';
        }

        $runbookExecution = [];
        if ($tickStatus === 'breach') {
            $runbookExecution[] = $this->executeRunbook('incident_response', $byUserId, [
                'tick_status' => $tickStatus,
                'evaluation_status' => $evaluationStatus,
                'critical_alerts' => $criticalAlerts,
            ]);
            $runbookExecution[] = $this->executeRunbook('degraded_mode', $byUserId, ['tick_status' => $tickStatus]);
            $runbookExecution[] = $this->executeRunbook('override_control', $byUserId, ['tick_status' => $tickStatus]);
        } elseif ($tickStatus === 'warning') {
            $runbookExecution[] = $this->executeRunbook('degraded_mode', $byUserId, ['tick_status' => $tickStatus]);
        } else {
            $runbookExecution[] = $this->executeRunbook('recovery_validation', $byUserId, ['tick_status' => $tickStatus]);
        }

        $state['last_tick_at'] = now()->toIso8601String();
        $state['last_tick_status'] = $tickStatus;
        $state['last_report_summary'] = [
            'evaluation_status' => $evaluationStatus,
            'evaluation_alert_count' => (int) ($evaluation['alert_count'] ?? 0),
            'governance_alert_count' => (int) ($alerts['count'] ?? 0),
            'critical_alert_count' => $criticalAlerts,
            'codeops_status' => (string) data_get($canary, 'state.status', data_get($canary, 'status', 'unknown')),
        ];

        if ($tickStatus === 'breach') {
            $state['consecutive_breach_count'] = (int) ($state['consecutive_breach_count'] ?? 0) + 1;
        } else {
            $state['consecutive_breach_count'] = 0;
        }

        $halted = false;
        $maxConsecutive = max(1, (int) config('najm-hoda.runtime.autonomy.operations_24x7.stop.after_consecutive_breaches', 2));
        if ((int) ($state['consecutive_breach_count'] ?? 0) >= $maxConsecutive) {
            $halted = true;
            $this->safeStop($byUserId, 'consecutive_breach_threshold_reached');
            $state['status'] = 'halted';
            $state['halted_at'] = now()->toIso8601String();
            $state['halted_reason'] = 'consecutive_breach_threshold_reached';
        }

        $state['updated_at'] = now()->toIso8601String();
        $this->storeState($state);

        $report = [
            'success' => true,
            'active' => true,
            'status' => $tickStatus,
            'halted' => $halted,
            'state' => $state,
            'evaluation' => $evaluation,
            'canary' => $canary,
            'governance_alerts' => $alerts,
            'runbook_execution' => $runbookExecution,
        ];

        $this->appendHistory([
            'type' => 'tick',
            'timestamp' => now()->toIso8601String(),
            'status' => $tickStatus,
            'halted' => $halted,
            'evaluation_status' => $evaluationStatus,
            'critical_alert_count' => $criticalAlerts,
            'consecutive_breach_count' => (int) ($state['consecutive_breach_count'] ?? 0),
        ]);

        $this->eventBus->emit('najm_hoda.autonomy.operations_24x7.tick.completed', [
            'status' => $tickStatus,
            'halted' => $halted,
            'evaluation_status' => $evaluationStatus,
            'critical_alert_count' => $criticalAlerts,
            'consecutive_breach_count' => (int) ($state['consecutive_breach_count'] ?? 0),
        ]);

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $state = Cache::get($this->stateKey, []);
        if (!is_array($state)) {
            $state = [];
        }

        return array_merge([
            'status' => 'inactive',
            'mode' => 'night_only',
            'activated_at' => null,
            'activated_by' => null,
            'deactivated_at' => null,
            'deactivated_by' => null,
            'deactivation_reason' => null,
            'reason' => null,
            'last_tick_at' => null,
            'last_tick_status' => null,
            'consecutive_breach_count' => 0,
            'last_report_summary' => null,
            'halted_at' => null,
            'halted_reason' => null,
            'updated_at' => null,
        ], $state);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(int $limit = 50): array
    {
        $limit = max(1, min(300, $limit));
        $rows = Cache::get($this->historyKey, []);
        if (!is_array($rows)) {
            return [];
        }

        return array_slice($rows, 0, $limit);
    }

    protected function normalizeMode(?string $mode): string
    {
        $mode = strtolower(trim((string) $mode));
        if (!in_array($mode, ['night_only', 'always'], true)) {
            $mode = 'night_only';
        }
        return $mode;
    }

    protected function isInVirtualShift(string $mode): bool
    {
        if ($mode === 'always') {
            return true;
        }

        $start = max(0, min(23, (int) config('najm-hoda.runtime.autonomy.operations_24x7.virtual_shift.start_hour', 0)));
        $end = max(0, min(23, (int) config('najm-hoda.runtime.autonomy.operations_24x7.virtual_shift.end_hour', 8)));
        $hour = (int) now()->format('G');

        if ($start === $end) {
            return true;
        }
        if ($start < $end) {
            return $hour >= $start && $hour < $end;
        }

        return $hour >= $start || $hour < $end;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function executeRunbook(string $runbookId, ?int $byUserId, array $context = []): array
    {
        $runbook = $this->findRunbook($runbookId);
        if ($runbook === null) {
            return [
                'runbook_id' => $runbookId,
                'status' => 'missing',
            ];
        }

        if ((string) ($runbook['status'] ?? 'draft') !== 'active') {
            return [
                'runbook_id' => $runbookId,
                'status' => 'inactive',
            ];
        }

        $effects = [];
        if ($runbookId === 'incident_response') {
            if ((bool) config('najm-hoda.runtime.autonomy.operations_24x7.runbook_effects.incident_response.activate_kill_switch', true)) {
                $effects['kill_switch'] = $this->controlService->activateKillSwitch(
                    $byUserId,
                    'ops_24x7_incident_response',
                    max(5, (int) config('najm-hoda.runtime.autonomy.operations_24x7.runbook_effects.incident_response.kill_switch_minutes', 30))
                );
            }
        } elseif ($runbookId === 'degraded_mode') {
            $effects['override'] = $this->controlService->setOverride(
                'propose',
                [],
                false,
                $byUserId,
                'ops_24x7_degraded_mode'
            );
        } elseif ($runbookId === 'override_control') {
            $effects['override'] = $this->controlService->setOverride(
                'propose',
                [],
                false,
                $byUserId,
                'ops_24x7_override_control'
            );
        } elseif ($runbookId === 'recovery_validation') {
            if ((bool) config('najm-hoda.runtime.autonomy.operations_24x7.runbook_effects.recovery_validation.auto_resume', false)) {
                $effects['resume'] = $this->controlService->resume($byUserId, 'ops_24x7_recovery_validation');
                $effects['kill_switch'] = $this->controlService->deactivateKillSwitch($byUserId, 'ops_24x7_recovery_validation');
                $effects['override'] = $this->controlService->clearOverride($byUserId, 'ops_24x7_recovery_validation');
            }
        }

        $result = [
            'runbook_id' => $runbookId,
            'status' => 'executed',
            'checklist_count' => count((array) ($runbook['checklist'] ?? [])),
            'effects' => $effects,
            'context' => $context,
            'executed_at' => now()->toIso8601String(),
        ];

        $this->eventBus->emit('najm_hoda.autonomy.operations_24x7.runbook.executed', [
            'runbook_id' => $runbookId,
            'status' => 'executed',
            'context' => $context,
        ]);

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function findRunbook(string $runbookId): ?array
    {
        foreach ($this->runbookService->all() as $runbook) {
            if ((string) ($runbook['id'] ?? '') === $runbookId) {
                return $runbook;
            }
        }
        return null;
    }

    protected function safeStop(?int $byUserId, string $reason): void
    {
        $this->controlService->setOverride('propose', [], false, $byUserId, 'ops_24x7_safe_stop');
        $this->controlService->pause(
            $byUserId,
            'ops_24x7_safe_stop',
            max(5, (int) config('najm-hoda.runtime.autonomy.operations_24x7.stop.pause_minutes', 60))
        );
        if ((bool) config('najm-hoda.runtime.autonomy.operations_24x7.stop.activate_kill_switch', true)) {
            $this->controlService->activateKillSwitch(
                $byUserId,
                'ops_24x7_safe_stop',
                max(5, (int) config('najm-hoda.runtime.autonomy.operations_24x7.stop.kill_switch_minutes', 60))
            );
        }

        $this->eventBus->emit('najm_hoda.autonomy.operations_24x7.safe_stopped', [
            'reason' => $reason,
            'by_user_id' => $byUserId,
        ]);
    }

    /**
     * @param array<string, mixed> $state
     */
    protected function storeState(array $state): void
    {
        Cache::put(
            $this->stateKey,
            $state,
            now()->addMinutes(max(60, (int) config('najm-hoda.runtime.autonomy.operations_24x7.retention_minutes', 20160)))
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function appendHistory(array $row): void
    {
        $rows = Cache::get($this->historyKey, []);
        if (!is_array($rows)) {
            $rows = [];
        }
        array_unshift($rows, $row);
        $rows = array_slice(
            $rows,
            0,
            max(20, (int) config('najm-hoda.runtime.autonomy.operations_24x7.history_size', 500))
        );
        Cache::put(
            $this->historyKey,
            $rows,
            now()->addMinutes(max(60, (int) config('najm-hoda.runtime.autonomy.operations_24x7.retention_minutes', 20160)))
        );
    }
}

