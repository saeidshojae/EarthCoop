<?php

namespace App\Services\NajmHoda\Runtime;

class NajmHodaProductionReadinessService
{
    public function __construct(
        protected RuntimeEventBus $eventBus,
        protected NajmHodaGovernanceMetricsAggregatorService $governanceMetrics,
        protected NajmHodaDecisionPolicyDriftService $driftService,
        protected NajmHodaRunbookRegistryService $runbookService,
        protected NajmHodaAutonomyApprovalService $approvalService,
        protected NajmHodaAutonomyGameDayService $gameDayService,
        protected NajmHodaComplianceEvidenceService $complianceService
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function review(?int $windowHours = null): array
    {
        $windowHours = $windowHours ?? (int) config('najm-hoda.runtime.autonomy.readiness.window_hours', 24);
        $windowHours = max(1, min(720, $windowHours));

        $snapshot = $this->governanceMetrics->snapshot($windowHours);
        $drift = $this->driftService->report($windowHours);
        $runbookReadiness = $this->runbookService->readiness();
        $runbooks = $this->runbookService->all();
        $pendingApprovals = $this->approvalService->pending(500);
        $gamedayHistory = $this->gameDayService->history((int) config('najm-hoda.runtime.autonomy.readiness.gameday.history_limit', 10));
        $evidencePack = $this->complianceService->buildPack($windowHours);

        $readinessChecks = [
            'governance' => $this->evaluateGovernance($snapshot),
            'drift' => $this->evaluateDrift($drift),
            'runbooks' => $this->evaluateRunbooks($runbookReadiness, $runbooks),
            'approvals' => $this->evaluateApprovals($pendingApprovals),
            'gameday' => $this->evaluateGameDay($gamedayHistory),
            'evidence' => $this->evaluateEvidence($evidencePack),
        ];

        $blockers = [];
        $warnings = [];

        foreach ($readinessChecks as $name => $check) {
            $status = (string) ($check['status'] ?? 'warning');
            $issues = is_array($check['issues'] ?? null) ? $check['issues'] : [];
            if ($status === 'blocker') {
                $blockers[$name] = $issues;
                continue;
            }
            if ($status === 'warning') {
                $warnings[$name] = $issues;
            }
        }

        $decision = 'go';
        if (!empty($blockers)) {
            $decision = 'no_go';
        } elseif (!empty($warnings)) {
            $decision = 'conditional_go';
        }

        $report = [
            'generated_at' => now()->toIso8601String(),
            'window_hours' => $windowHours,
            'decision' => $decision,
            'blocker_count' => count($blockers),
            'warning_count' => count($warnings),
            'checks' => $readinessChecks,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'rollback_plan' => [
                'runbook_ids' => ['incident_response', 'degraded_mode', 'override_control', 'recovery_validation'],
                'ready' => ((string) ($readinessChecks['runbooks']['status'] ?? 'warning')) === 'ok',
            ],
            'evidence' => [
                'integrity_hash' => (string) data_get($evidencePack, 'meta.integrity_hash', ''),
                'summary' => (array) data_get($evidencePack, 'summary', []),
            ],
        ];

        $this->eventBus->emit('najm_hoda.autonomy.readiness.reviewed', [
            'decision' => $decision,
            'blocker_count' => count($blockers),
            'warning_count' => count($warnings),
            'window_hours' => $windowHours,
        ]);

        return $report;
    }

    public function exportJson(?int $windowHours = null): string
    {
        $report = $this->review($windowHours);
        return (string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    protected function evaluateGovernance(array $snapshot): array
    {
        $evaluation = is_array($snapshot['evaluation'] ?? null) ? $snapshot['evaluation'] : [];
        $breach = 0;
        $warning = 0;
        foreach ($evaluation as $item) {
            if (!is_array($item)) {
                continue;
            }
            $status = (string) ($item['status'] ?? 'ok');
            if ($status === 'breach') {
                $breach++;
            } elseif ($status === 'warning') {
                $warning++;
            }
        }

        $maxBreach = max(0, (int) config('najm-hoda.runtime.autonomy.readiness.governance.max_breach_kpis', 0));
        $maxWarning = max(0, (int) config('najm-hoda.runtime.autonomy.readiness.governance.max_warning_kpis', 2));
        $issues = [];
        $status = 'ok';

        if ($breach > $maxBreach) {
            $status = 'blocker';
            $issues[] = 'governance_breach_kpis_exceeded';
        } elseif ($warning > $maxWarning) {
            $status = 'warning';
            $issues[] = 'governance_warning_kpis_exceeded';
        }

        return [
            'status' => $status,
            'issues' => $issues,
            'metrics' => [
                'breach_kpis' => $breach,
                'warning_kpis' => $warning,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $drift
     * @return array<string, mixed>
     */
    protected function evaluateDrift(array $drift): array
    {
        $status = (string) ($drift['status'] ?? 'ok');
        $issues = [];
        $result = 'ok';

        if ($status === 'breach') {
            $result = 'blocker';
            $issues[] = 'policy_drift_breach';
        } elseif ($status === 'warning') {
            $result = 'warning';
            $issues[] = 'policy_drift_warning';
        }

        return [
            'status' => $result,
            'issues' => $issues,
            'metrics' => [
                'drift_rate' => (float) ($drift['drift_rate'] ?? 0.0),
                'drift_count' => (int) ($drift['drift_count'] ?? 0),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $readiness
     * @param array<int, array<string, mixed>> $runbooks
     * @return array<string, mixed>
     */
    protected function evaluateRunbooks(array $readiness, array $runbooks): array
    {
        $status = (string) ($readiness['status'] ?? 'breach');
        $issues = [];
        $result = $status === 'ready' ? 'ok' : ($status === 'warning' ? 'warning' : 'blocker');

        if ($result !== 'ok') {
            $issues[] = 'runbook_readiness_not_ready';
        }

        $required = (array) config('najm-hoda.runtime.autonomy.readiness.rollback.required_runbooks', [
            'incident_response',
            'degraded_mode',
            'override_control',
            'recovery_validation',
        ]);
        $required = array_values(array_filter(array_map(static fn ($id): string => trim((string) $id), $required), static fn (string $id): bool => $id !== ''));
        $available = [];
        foreach ($runbooks as $runbook) {
            $id = (string) ($runbook['id'] ?? '');
            if ($id !== '') {
                $available[$id] = (string) ($runbook['status'] ?? 'draft');
            }
        }

        $missing = [];
        foreach ($required as $id) {
            if (!isset($available[$id]) || $available[$id] !== 'active') {
                $missing[] = $id;
            }
        }
        if (!empty($missing)) {
            $result = 'blocker';
            $issues[] = 'rollback_runbooks_missing_or_inactive';
        }

        return [
            'status' => $result,
            'issues' => $issues,
            'metrics' => [
                'readiness_ratio' => (float) ($readiness['readiness_ratio'] ?? 0.0),
                'missing_required_runbooks' => $missing,
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $pendingApprovals
     * @return array<string, mixed>
     */
    protected function evaluateApprovals(array $pendingApprovals): array
    {
        $pending = count($pendingApprovals);
        $overdue = count(array_filter($pendingApprovals, static fn (array $item): bool => (string) ($item['sla_status'] ?? '') === 'overdue'));

        $maxPending = max(0, (int) config('najm-hoda.runtime.autonomy.readiness.approvals.max_pending', 25));
        $maxOverdue = max(0, (int) config('najm-hoda.runtime.autonomy.readiness.approvals.max_overdue', 0));
        $issues = [];
        $status = 'ok';

        if ($overdue > $maxOverdue) {
            $status = 'blocker';
            $issues[] = 'approval_overdue_exceeded';
        } elseif ($pending > $maxPending) {
            $status = 'warning';
            $issues[] = 'approval_pending_exceeded';
        }

        return [
            'status' => $status,
            'issues' => $issues,
            'metrics' => [
                'pending' => $pending,
                'overdue' => $overdue,
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $history
     * @return array<string, mixed>
     */
    protected function evaluateGameDay(array $history): array
    {
        $minCycles = max(1, (int) config('najm-hoda.runtime.autonomy.readiness.gameday.min_cycles', 2));
        $requiredPassRate = max(0.0, min(1.0, (float) config('najm-hoda.runtime.autonomy.readiness.gameday.min_pass_rate', 1.0)));
        $recent = array_slice($history, 0, $minCycles);
        $cycleCount = count($recent);
        $passCount = count(array_filter($recent, static fn (array $item): bool => (string) ($item['status'] ?? '') === 'pass'));
        $passRate = $cycleCount > 0 ? round($passCount / $cycleCount, 4) : 0.0;

        $issues = [];
        $status = 'ok';
        if ($cycleCount < $minCycles) {
            $status = 'blocker';
            $issues[] = 'gameday_cycles_insufficient';
        } elseif ($passRate < $requiredPassRate) {
            $status = 'blocker';
            $issues[] = 'gameday_pass_rate_below_threshold';
        }

        return [
            'status' => $status,
            'issues' => $issues,
            'metrics' => [
                'required_cycles' => $minCycles,
                'cycle_count' => $cycleCount,
                'pass_count' => $passCount,
                'pass_rate' => $passRate,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $pack
     * @return array<string, mixed>
     */
    protected function evaluateEvidence(array $pack): array
    {
        $summary = is_array($pack['summary'] ?? null) ? $pack['summary'] : [];
        $integrity = trim((string) data_get($pack, 'meta.integrity_hash', ''));
        $minAudit = max(0, (int) config('najm-hoda.runtime.autonomy.readiness.evidence.min_audit_traces', 1));
        $minEvents = max(0, (int) config('najm-hoda.runtime.autonomy.readiness.evidence.min_runtime_events', 1));

        $issues = [];
        $status = 'ok';
        if ($integrity === '') {
            $status = 'blocker';
            $issues[] = 'evidence_integrity_hash_missing';
        }
        if ((int) ($summary['audit_traces'] ?? 0) < $minAudit) {
            $status = 'blocker';
            $issues[] = 'evidence_audit_traces_insufficient';
        }
        if ((int) ($summary['runtime_events'] ?? 0) < $minEvents) {
            if ($status !== 'blocker') {
                $status = 'warning';
            }
            $issues[] = 'evidence_runtime_events_low';
        }

        return [
            'status' => $status,
            'issues' => $issues,
            'metrics' => [
                'audit_traces' => (int) ($summary['audit_traces'] ?? 0),
                'runtime_events' => (int) ($summary['runtime_events'] ?? 0),
            ],
        ];
    }
}
