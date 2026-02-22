<?php

namespace App\Services\NajmHoda\Runtime;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;

class NajmHodaContinuousEvaluationHarnessService
{
    protected string $lastReportKey = 'najm_hoda:autonomy:evaluation:last_report';
    protected string $historyKey = 'najm_hoda:autonomy:evaluation:history';
    protected string $alertsHistoryKey = 'najm_hoda:autonomy:evaluation:alerts:history';

    public function __construct(
        protected RuntimeEventBus $eventBus,
        protected NajmHodaGovernanceMetricsAggregatorService $governanceMetrics,
        protected NajmHodaDecisionPolicyDriftService $driftService,
        protected NajmHodaAutonomyAuditService $auditService,
        protected NotificationService $notificationService
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function run(?int $windowHours = null, bool $dryRun = false): array
    {
        $windowHours = max(1, min(720, $windowHours ?? (int) config('najm-hoda.runtime.autonomy.evaluation.window_hours', 24)));
        $snapshot = $this->governanceMetrics->snapshot($windowHours);
        $drift = $this->driftService->report($windowHours);
        $audit = $this->auditService->history(max(20, (int) config('najm-hoda.runtime.autonomy.evaluation.audit_limit', 200)));
        $previous = $this->lastReport();

        $decisionQuality = $this->decisionQualityScore($snapshot);
        $safetyRegression = $this->safetyRegression($snapshot, $audit, $previous);
        $driftTrend = $this->driftTrend($drift, $previous);
        $alerts = $this->buildAlerts($decisionQuality, $safetyRegression, $driftTrend, $snapshot, $drift);
        $status = $this->resolveStatus($alerts);

        $report = [
            'generated_at' => now()->toIso8601String(),
            'window_hours' => $windowHours,
            'status' => $status,
            'decision_quality' => $decisionQuality,
            'safety_regression' => $safetyRegression,
            'drift_trend' => $driftTrend,
            'alerts' => $alerts,
            'alert_count' => count($alerts),
            'snapshot_summary' => [
                'event_count' => (int) ($snapshot['event_count'] ?? 0),
                'breach_kpis' => $this->countKpiStatus((array) ($snapshot['evaluation'] ?? []), 'breach'),
                'warning_kpis' => $this->countKpiStatus((array) ($snapshot['evaluation'] ?? []), 'warning'),
            ],
        ];

        $this->eventBus->emit('najm_hoda.autonomy.evaluation.completed', [
            'status' => $status,
            'window_hours' => $windowHours,
            'alert_count' => count($alerts),
            'decision_quality_score' => (float) ($decisionQuality['score'] ?? 0.0),
            'safety_regression' => (string) ($safetyRegression['status'] ?? 'ok'),
            'drift_status' => (string) ($driftTrend['status'] ?? 'ok'),
            'dry_run' => $dryRun,
        ]);

        if (!$dryRun) {
            $this->storeReport($report);
            if (!empty($alerts)) {
                $this->storeAlerts($alerts);
                if ((bool) config('najm-hoda.runtime.autonomy.evaluation.notify_admins', true)) {
                    $this->notifyAdmins($alerts, $status);
                }
            }
        }

        return $report;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastReport(): ?array
    {
        $data = Cache::get($this->lastReportKey);
        return is_array($data) ? $data : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(int $limit = 30): array
    {
        $limit = max(1, min(300, $limit));
        $rows = Cache::get($this->historyKey, []);
        if (!is_array($rows)) {
            return [];
        }
        return array_slice($rows, 0, $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function alertsHistory(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $rows = Cache::get($this->alertsHistoryKey, []);
        if (!is_array($rows)) {
            return [];
        }
        return array_slice($rows, 0, $limit);
    }

    public function exportJson(?int $windowHours = null): string
    {
        return (string) json_encode(
            $this->run($windowHours, true),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    protected function decisionQualityScore(array $snapshot): array
    {
        $successRate = (float) data_get($snapshot, 'metrics.auto_action_success_rate', 0.0);
        $satisfaction = (float) data_get($snapshot, 'metrics.user_satisfaction_score', 0.0);
        $latency = (float) data_get($snapshot, 'metrics.human_approval_latency_minutes', 0.0);

        $latencyTarget = max(1.0, (float) config('najm-hoda.runtime.autonomy.governance.kpis.human_approval_latency_minutes.target_max', 30));
        $latencyScore = 1.0 - min(1.0, $latency / ($latencyTarget * 2));
        $score = max(0.0, min(1.0, round(($successRate * 0.5) + ($satisfaction * 0.3) + ($latencyScore * 0.2), 4)));

        $status = 'ok';
        $min = (float) config('najm-hoda.runtime.autonomy.evaluation.thresholds.decision_quality_min', 0.75);
        $warn = (float) config('najm-hoda.runtime.autonomy.evaluation.thresholds.decision_quality_warning_below', 0.65);
        if ($score < $warn) {
            $status = 'breach';
        } elseif ($score < $min) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'score' => $score,
            'components' => [
                'success_rate' => $successRate,
                'user_satisfaction' => $satisfaction,
                'approval_latency_score' => round($latencyScore, 4),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $snapshot
     * @param array<int, array<string, mixed>> $audit
     * @param array<string, mixed>|null $previous
     * @return array<string, mixed>
     */
    protected function safetyRegression(array $snapshot, array $audit, ?array $previous): array
    {
        $failedRuns = count(array_filter($audit, static fn (array $row): bool => (string) ($row['status'] ?? '') === 'failed'));
        $totalRuns = max(1, count($audit));
        $failureRate = round($failedRuns / $totalRuns, 4);
        $breachKpis = $this->countKpiStatus((array) ($snapshot['evaluation'] ?? []), 'breach');

        $previousFailureRate = (float) data_get($previous, 'safety_regression.failure_rate', 0.0);
        $delta = round($failureRate - $previousFailureRate, 4);
        $maxFailure = (float) config('najm-hoda.runtime.autonomy.evaluation.thresholds.safety_failure_rate_max', 0.2);
        $maxDelta = (float) config('najm-hoda.runtime.autonomy.evaluation.thresholds.safety_failure_rate_delta_max', 0.1);

        $status = 'ok';
        $issues = [];
        if ($failureRate > $maxFailure) {
            $status = 'breach';
            $issues[] = 'failure_rate_above_threshold';
        } elseif ($delta > $maxDelta) {
            $status = 'warning';
            $issues[] = 'failure_rate_regression';
        }
        if ($breachKpis > 0) {
            if ($status !== 'breach') {
                $status = 'warning';
            }
            $issues[] = 'governance_breach_present';
        }

        return [
            'status' => $status,
            'issues' => $issues,
            'failure_rate' => $failureRate,
            'failure_rate_delta' => $delta,
            'breach_kpis' => $breachKpis,
        ];
    }

    /**
     * @param array<string, mixed> $drift
     * @param array<string, mixed>|null $previous
     * @return array<string, mixed>
     */
    protected function driftTrend(array $drift, ?array $previous): array
    {
        $rate = (float) ($drift['drift_rate'] ?? 0.0);
        $status = (string) ($drift['status'] ?? 'ok');
        $prevRate = (float) data_get($previous, 'drift_trend.rate', 0.0);
        $delta = round($rate - $prevRate, 4);
        $deltaWarn = (float) config('najm-hoda.runtime.autonomy.evaluation.thresholds.drift_delta_warning_above', 0.01);

        $trend = 'stable';
        if ($delta > $deltaWarn) {
            $trend = 'regressing';
        } elseif ($delta < 0) {
            $trend = 'improving';
        }

        return [
            'status' => $status,
            'trend' => $trend,
            'rate' => $rate,
            'delta' => $delta,
            'count' => (int) ($drift['drift_count'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $decisionQuality
     * @param array<string, mixed> $safetyRegression
     * @param array<string, mixed> $driftTrend
     * @param array<string, mixed> $snapshot
     * @param array<string, mixed> $drift
     * @return array<int, array<string, mixed>>
     */
    protected function buildAlerts(
        array $decisionQuality,
        array $safetyRegression,
        array $driftTrend,
        array $snapshot,
        array $drift
    ): array {
        $alerts = [];
        if ((string) ($decisionQuality['status'] ?? 'ok') !== 'ok') {
            $alerts[] = [
                'code' => 'EVAL_DECISION_QUALITY_' . strtoupper((string) ($decisionQuality['status'] ?? 'warning')),
                'severity' => (string) ($decisionQuality['status'] ?? 'warning') === 'breach' ? 'critical' : 'warning',
                'source' => 'decision_quality',
                'message' => 'Decision quality score is below expected threshold.',
                'value' => (float) ($decisionQuality['score'] ?? 0.0),
            ];
        }

        if ((string) ($safetyRegression['status'] ?? 'ok') !== 'ok') {
            $alerts[] = [
                'code' => 'EVAL_SAFETY_REGRESSION_' . strtoupper((string) ($safetyRegression['status'] ?? 'warning')),
                'severity' => (string) ($safetyRegression['status'] ?? 'warning') === 'breach' ? 'critical' : 'warning',
                'source' => 'safety_regression',
                'message' => 'Safety regression indicators detected.',
                'value' => (float) ($safetyRegression['failure_rate'] ?? 0.0),
                'issues' => (array) ($safetyRegression['issues'] ?? []),
            ];
        }

        if (
            (string) ($driftTrend['status'] ?? 'ok') === 'breach'
            || (string) ($driftTrend['trend'] ?? 'stable') === 'regressing'
        ) {
            $alerts[] = [
                'code' => 'EVAL_POLICY_DRIFT_' . strtoupper((string) ($driftTrend['status'] ?? 'warning')),
                'severity' => (string) ($driftTrend['status'] ?? 'ok') === 'breach' ? 'critical' : 'warning',
                'source' => 'policy_drift',
                'message' => 'Policy drift indicates unstable autonomy behavior.',
                'value' => (float) ($driftTrend['rate'] ?? (float) ($drift['drift_rate'] ?? 0.0)),
                'top_reasons' => (array) ($drift['top_reasons'] ?? []),
            ];
        }

        $breachKpis = $this->countKpiStatus((array) ($snapshot['evaluation'] ?? []), 'breach');
        if ($breachKpis > 0) {
            $alerts[] = [
                'code' => 'EVAL_GOVERNANCE_BREACH_KPIS',
                'severity' => 'warning',
                'source' => 'governance_snapshot',
                'message' => 'Governance snapshot contains breach KPI entries.',
                'value' => $breachKpis,
            ];
        }

        foreach ($alerts as $i => $alert) {
            $alerts[$i]['raised_at'] = now()->toIso8601String();
        }

        return $alerts;
    }

    /**
     * @param array<int, array<string, mixed>> $alerts
     */
    protected function resolveStatus(array $alerts): string
    {
        if (empty($alerts)) {
            return 'ok';
        }

        $hasCritical = (bool) array_filter($alerts, static fn (array $row): bool => (string) ($row['severity'] ?? '') === 'critical');
        return $hasCritical ? 'breach' : 'warning';
    }

    /**
     * @param array<string, mixed> $evaluation
     */
    protected function countKpiStatus(array $evaluation, string $status): int
    {
        $count = 0;
        foreach ($evaluation as $row) {
            if ((string) data_get($row, 'status', '') === $status) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * @param array<string, mixed> $report
     */
    protected function storeReport(array $report): void
    {
        $ttlMinutes = max(60, (int) config('najm-hoda.runtime.autonomy.evaluation.retention_minutes', 20160));
        Cache::put($this->lastReportKey, $report, now()->addMinutes($ttlMinutes));

        $history = Cache::get($this->historyKey, []);
        if (!is_array($history)) {
            $history = [];
        }
        array_unshift($history, $report);
        $history = array_slice($history, 0, max(20, (int) config('najm-hoda.runtime.autonomy.evaluation.history_size', 180)));
        Cache::put($this->historyKey, $history, now()->addMinutes($ttlMinutes));
    }

    /**
     * @param array<int, array<string, mixed>> $alerts
     */
    protected function storeAlerts(array $alerts): void
    {
        $history = Cache::get($this->alertsHistoryKey, []);
        if (!is_array($history)) {
            $history = [];
        }
        foreach ($alerts as $alert) {
            array_unshift($history, $alert);
        }
        $history = array_slice($history, 0, max(20, (int) config('najm-hoda.runtime.autonomy.evaluation.alerts_history_size', 500)));
        Cache::put(
            $this->alertsHistoryKey,
            $history,
            now()->addMinutes(max(60, (int) config('najm-hoda.runtime.autonomy.evaluation.retention_minutes', 20160)))
        );
    }

    /**
     * @param array<int, array<string, mixed>> $alerts
     */
    protected function notifyAdmins(array $alerts, string $status): void
    {
        $adminIds = User::query()
            ->where('is_admin', 1)
            ->orWhereHas('roles', function ($query): void {
                $query->whereIn('slug', ['super-admin', 'support', 'support_agent']);
            })
            ->pluck('id')
            ->all();

        if (empty($adminIds)) {
            return;
        }

        $title = $status === 'breach'
            ? 'هشدار بحرانی ارزیابی شبانه نجم‌هدا'
            : 'هشدار ارزیابی شبانه نجم‌هدا';
        $message = 'ارزیابی شبانه خودگردانی با وضعیت ' . $status . ' و تعداد ' . count($alerts) . ' هشدار ثبت شد.';

        $this->notificationService->notifyMany(
            $adminIds,
            $title,
            $message,
            url('/admin/najm-hoda/autonomy/governance'),
            $status === 'breach' ? 'error' : 'warning',
            [
                'evaluation_status' => $status,
                'alerts_count' => count($alerts),
            ]
        );
    }
}

