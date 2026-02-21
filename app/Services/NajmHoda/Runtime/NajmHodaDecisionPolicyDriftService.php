<?php

namespace App\Services\NajmHoda\Runtime;

class NajmHodaDecisionPolicyDriftService
{
    public function __construct(
        protected RuntimeEventBus $eventBus
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function report(?int $windowHours = null): array
    {
        $windowHours = $windowHours ?? (int) config('najm-hoda.runtime.autonomy.governance.drift.window_hours', 24);
        $windowHours = max(1, $windowHours);

        $eventLimit = max(100, (int) config('najm-hoda.runtime.autonomy.governance.drift.event_limit', 3000));
        $events = $this->eventBus->recent(null, $eventLimit);
        $events = $this->filterByWindow($events, $windowHours);

        $totalDecisions = 0;
        $driftEvents = [];

        foreach ($events as $entry) {
            $name = (string) ($entry['event'] ?? '');
            $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];

            if (in_array($name, [
                'najm_hoda.autonomy.contract.accepted',
                'najm_hoda.autonomy.executor.executed',
                'najm_hoda.autonomy.executor.failed',
                'najm_hoda.autonomy.approval.decided',
            ], true)) {
                $totalDecisions++;
            }

            if (in_array($name, [
                'najm_hoda.autonomy.safety.blocked',
                'najm_hoda.autonomy.contract.rejected',
                'najm_hoda.autonomy.plan_item.blocked',
            ], true)) {
                $reason = (string) ($payload['reason'] ?? 'unknown');
                $severity = $this->severityForReason($reason);
                $driftEvents[] = [
                    'event' => $name,
                    'reason' => $reason,
                    'severity' => $severity,
                    'timestamp' => $entry['timestamp'] ?? null,
                ];
            }
        }

        $driftCount = count($driftEvents);
        $rate = $totalDecisions > 0 ? round($driftCount / $totalDecisions, 4) : 0.0;
        $thresholdWarning = (float) config('najm-hoda.runtime.autonomy.governance.kpis.policy_drift_rate.warning_above', 0.02);
        $thresholdBreach = (float) config('najm-hoda.runtime.autonomy.governance.kpis.policy_drift_rate.target_max', 0.01);
        $status = $rate > $thresholdWarning ? 'warning' : 'ok';
        if ($rate > $thresholdBreach) {
            $status = 'breach';
        }

        $report = [
            'generated_at' => now()->toIso8601String(),
            'window_hours' => $windowHours,
            'total_decisions' => $totalDecisions,
            'drift_count' => $driftCount,
            'drift_rate' => $rate,
            'status' => $status,
            'top_reasons' => $this->topReasons($driftEvents),
            'events' => array_slice($driftEvents, 0, 100),
        ];

        $this->eventBus->emit('najm_hoda.autonomy.governance.drift.reported', [
            'window_hours' => $windowHours,
            'drift_rate' => $rate,
            'status' => $status,
            'drift_count' => $driftCount,
        ]);

        return $report;
    }

    /**
     * @param array<int, array<string, mixed>> $events
     * @return array<int, array<string, mixed>>
     */
    protected function filterByWindow(array $events, int $windowHours): array
    {
        $cutoff = now()->subHours($windowHours);
        return array_values(array_filter($events, static function (array $event) use ($cutoff): bool {
            $timestamp = $event['timestamp'] ?? null;
            if (!is_string($timestamp) || trim($timestamp) === '') {
                return false;
            }
            try {
                return \Carbon\CarbonImmutable::parse($timestamp)->greaterThanOrEqualTo($cutoff);
            } catch (\Throwable) {
                return false;
            }
        }));
    }

    protected function severityForReason(string $reason): string
    {
        $criticalReasons = ['high_risk_blocked', 'daily_budget_exceeded', 'monthly_budget_exceeded'];
        if (in_array($reason, $criticalReasons, true)) {
            return 'high';
        }
        return 'medium';
    }

    /**
     * @param array<int, array<string, mixed>> $driftEvents
     * @return array<int, array<string, mixed>>
     */
    protected function topReasons(array $driftEvents): array
    {
        $map = [];
        foreach ($driftEvents as $item) {
            $reason = (string) ($item['reason'] ?? 'unknown');
            $map[$reason] = ($map[$reason] ?? 0) + 1;
        }
        arsort($map);

        $result = [];
        foreach (array_slice($map, 0, 5, true) as $reason => $count) {
            $result[] = ['reason' => $reason, 'count' => $count];
        }
        return $result;
    }
}
