<?php

namespace App\Services\NajmHoda\Runtime;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;

class NajmHodaGovernanceAlertingService
{
    protected string $historyKey = 'najm_hoda:autonomy:governance:alerts:history';
    protected string $cooldownPrefix = 'najm_hoda:autonomy:governance:alerts:cooldown:';

    public function __construct(
        protected RuntimeEventBus $eventBus,
        protected NajmHodaGovernanceMetricsAggregatorService $metricsAggregator,
        protected NajmHodaAutonomyApprovalService $approvalService,
        protected NotificationService $notificationService
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function evaluateAndAlert(?int $windowHours = null, bool $dryRun = false): array
    {
        if (!(bool) config('najm-hoda.runtime.autonomy.governance.alerting.enabled', true)) {
            return [
                'alerts' => [],
                'count' => 0,
                'status' => 'disabled',
            ];
        }

        $windowHours = $windowHours ?? (int) config('najm-hoda.runtime.autonomy.governance.window_hours', 24);
        $windowHours = max(1, $windowHours);
        $snapshot = $this->metricsAggregator->snapshot($windowHours);
        $alerts = $this->buildCandidates($snapshot);
        $maxPerRun = max(1, (int) config('najm-hoda.runtime.autonomy.governance.alerting.max_alerts_per_run', 20));
        $alerts = array_slice($alerts, 0, $maxPerRun);

        $raised = [];
        foreach ($alerts as $candidate) {
            $code = (string) ($candidate['code'] ?? 'GOV_UNKNOWN');
            $cooldownKey = $this->cooldownPrefix . $code;

            if (Cache::has($cooldownKey)) {
                $this->eventBus->emit('najm_hoda.autonomy.governance.alert.skipped', [
                    'code' => $code,
                    'reason' => 'cooldown_active',
                ]);
                continue;
            }

            if ($dryRun) {
                $candidate['status'] = 'dry_run';
                $raised[] = $candidate;
                continue;
            }

            $candidate['status'] = 'raised';
            $raised[] = $candidate;

            $cooldownMinutes = max(1, (int) config('najm-hoda.runtime.autonomy.governance.alerting.cooldown_minutes', 30));
            Cache::put($cooldownKey, 1, now()->addMinutes($cooldownMinutes));
            $this->eventBus->emit('najm_hoda.autonomy.governance.alert.raised', $candidate);
        }

        if (!$dryRun && !empty($raised)) {
            $this->storeHistory($raised);
            if ((bool) config('najm-hoda.runtime.autonomy.governance.alerting.notify_admins', true)) {
                $this->notifyAdmins($raised);
            }
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'window_hours' => $windowHours,
            'dry_run' => $dryRun,
            'alerts' => $raised,
            'count' => count($raised),
            'status' => 'ok',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function history(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $history = Cache::get($this->historyKey, []);
        if (!is_array($history)) {
            return [];
        }

        return array_slice($history, 0, $limit);
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<int, array<string, mixed>>
     */
    protected function buildCandidates(array $snapshot): array
    {
        $evaluation = is_array($snapshot['evaluation'] ?? null) ? $snapshot['evaluation'] : [];
        $alerts = [];

        foreach ($evaluation as $kpi => $item) {
            if (!is_array($item)) {
                continue;
            }

            $status = (string) ($item['status'] ?? 'ok');
            if (!in_array($status, ['warning', 'breach'], true)) {
                continue;
            }

            $severity = $status === 'breach' ? 'critical' : 'warning';
            $alerts[] = [
                'code' => 'GOV_KPI_' . strtoupper($kpi),
                'type' => 'kpi',
                'severity' => $severity,
                'kpi' => $kpi,
                'status' => $status,
                'value' => $item['value'] ?? null,
                'target_min' => $item['target_min'] ?? null,
                'target_max' => $item['target_max'] ?? null,
                'source' => 'governance_metrics_snapshot',
                'raised_at' => now()->toIso8601String(),
            ];
        }

        $overdueThreshold = max(1, (int) config('najm-hoda.runtime.autonomy.governance.alerting.approval_sla_overdue_threshold', 1));
        $pending = $this->approvalService->pending(200);
        $overdueCount = count(array_filter($pending, static function (array $request): bool {
            return (string) ($request['sla_status'] ?? '') === 'overdue';
        }));

        if ($overdueCount >= $overdueThreshold) {
            $alerts[] = [
                'code' => 'GOV_APPROVAL_SLA_OVERDUE',
                'type' => 'approval_sla',
                'severity' => 'critical',
                'kpi' => 'human_approval_latency_minutes',
                'status' => 'breach',
                'value' => $overdueCount,
                'target_min' => null,
                'target_max' => $overdueThreshold - 1,
                'source' => 'approval_queue',
                'raised_at' => now()->toIso8601String(),
            ];
        }

        return $alerts;
    }

    /**
     * @param array<int, array<string, mixed>> $alerts
     */
    protected function storeHistory(array $alerts): void
    {
        $history = Cache::get($this->historyKey, []);
        if (!is_array($history)) {
            $history = [];
        }

        foreach ($alerts as $alert) {
            array_unshift($history, $alert);
        }

        $maxHistory = max(20, (int) config('najm-hoda.runtime.autonomy.governance.alerting.max_history', 500));
        $history = array_slice($history, 0, $maxHistory);
        Cache::put($this->historyKey, $history, now()->addDays(14));
    }

    /**
     * @param array<int, array<string, mixed>> $alerts
     */
    protected function notifyAdmins(array $alerts): void
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

        $criticalCount = count(array_filter($alerts, static fn (array $a): bool => (string) ($a['severity'] ?? '') === 'critical'));
        $title = $criticalCount > 0
            ? 'هشدار بحرانی حاکمیت خودگردانی نجم‌هدا'
            : 'هشدار حاکمیت خودگردانی نجم‌هدا';
        $message = 'تعداد ' . count($alerts) . ' هشدار حاکمیتی ثبت شد. لطفاً وضعیت SLO و صف تایید انسانی را بررسی کنید.';

        $this->notificationService->notifyMany(
            $adminIds,
            $title,
            $message,
            url('/admin/najm-hoda/autonomy/governance'),
            $criticalCount > 0 ? 'error' : 'warning',
            [
                'alerts_count' => count($alerts),
                'critical_count' => $criticalCount,
            ]
        );
    }
}
