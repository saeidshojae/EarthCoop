<?php

namespace App\Services\NajmHoda\Runtime;

use App\Models\Feedback;
use Illuminate\Support\Facades\Cache;

class NajmHodaGovernanceMetricsAggregatorService
{
    public function __construct(
        protected RuntimeEventBus $eventBus,
        protected NajmHodaGovernanceKpiCatalogService $kpiCatalog
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(?int $windowHours = null): array
    {
        $windowHours = $windowHours ?? (int) config('najm-hoda.runtime.autonomy.governance.window_hours', 24);
        $windowHours = max(1, $windowHours);

        $eventLimit = max(100, (int) config('najm-hoda.runtime.autonomy.governance.event_limit', 3000));
        $events = $this->eventBus->recent(null, $eventLimit);
        $events = $this->filterByWindow($events, $windowHours);

        $metrics = $this->calculateMetrics($events, $windowHours);
        $baseline = $this->kpiCatalog->baseline();
        $evaluation = $this->evaluateMetrics($metrics, $baseline);

        $snapshot = [
            'generated_at' => now()->toIso8601String(),
            'window_hours' => $windowHours,
            'event_count' => count($events),
            'metrics' => $metrics,
            'evaluation' => $evaluation,
        ];

        $ttlMinutes = max(30, (int) config('najm-hoda.runtime.autonomy.governance.snapshot_ttl_minutes', 180));
        Cache::put('najm_hoda:autonomy:governance:last_snapshot', $snapshot, now()->addMinutes($ttlMinutes));

        return $snapshot;
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

    /**
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    protected function calculateMetrics(array $events, int $windowHours): array
    {
        $executed = 0;
        $failed = 0;
        $skipped = 0;
        $driftEvents = 0;
        $approvalRequested = [];
        $approvalLatencies = [];

        foreach ($events as $entry) {
            $name = (string) ($entry['event'] ?? '');
            $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];

            if ($name === 'najm_hoda.autonomy.executor.executed') {
                $executed++;
                continue;
            }
            if ($name === 'najm_hoda.autonomy.executor.failed') {
                $failed++;
                continue;
            }
            if ($name === 'najm_hoda.autonomy.executor.skipped') {
                $skipped++;
                continue;
            }

            if ($name === 'najm_hoda.autonomy.approval.requested') {
                $requestId = (string) ($payload['request_id'] ?? '');
                if ($requestId !== '') {
                    $approvalRequested[$requestId] = $entry['timestamp'] ?? null;
                }
                continue;
            }
            if ($name === 'najm_hoda.autonomy.approval.decided') {
                $requestId = (string) ($payload['request_id'] ?? '');
                if ($requestId !== '' && isset($approvalRequested[$requestId]) && is_string($approvalRequested[$requestId])) {
                    try {
                        $requestedAt = \Carbon\CarbonImmutable::parse((string) $approvalRequested[$requestId]);
                        $decidedAt = \Carbon\CarbonImmutable::parse((string) ($entry['timestamp'] ?? ''));
                        $approvalLatencies[] = max(0, $requestedAt->diffInMinutes($decidedAt));
                    } catch (\Throwable) {
                    }
                }
                continue;
            }

            if (in_array($name, [
                'najm_hoda.autonomy.safety.blocked',
                'najm_hoda.autonomy.contract.rejected',
                'najm_hoda.autonomy.plan_item.blocked',
            ], true)) {
                $driftEvents++;
            }
        }

        $executedTotal = $executed + $failed;
        $successRate = $executedTotal > 0 ? round($executed / $executedTotal, 4) : 1.0;
        $coverageDenominator = max(1, $executed + $failed + $skipped);
        $coverageRate = round($executed / $coverageDenominator, 4);
        $approvalLatency = !empty($approvalLatencies) ? round(array_sum($approvalLatencies) / count($approvalLatencies), 2) : 0.0;
        $policyDriftRate = $coverageDenominator > 0 ? round($driftEvents / $coverageDenominator, 4) : 0.0;

        $userSatisfaction = $this->resolveUserSatisfactionRatio($windowHours);

        return [
            'auto_action_success_rate' => $successRate,
            'autonomy_coverage_rate' => $coverageRate,
            'mttr_reduction_rate' => null,
            'rollback_unwanted_rate' => null,
            'user_satisfaction_score' => $userSatisfaction,
            'human_approval_latency_minutes' => $approvalLatency,
            'policy_drift_rate' => $policyDriftRate,
            'counters' => [
                'executed' => $executed,
                'failed' => $failed,
                'skipped' => $skipped,
                'drift_events' => $driftEvents,
                'approval_decisions' => count($approvalLatencies),
            ],
        ];
    }

    protected function resolveUserSatisfactionRatio(int $windowHours): float
    {
        try {
            $since = now()->subHours($windowHours);
            $avgRating = Feedback::query()
                ->where('created_at', '>=', $since)
                ->avg('rating');
            if ($avgRating === null) {
                return 0.0;
            }
            return round(max(0.0, min(1.0, ((float) $avgRating) / 5.0)), 4);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    /**
     * @param array<string, mixed> $metrics
     * @param array<string, mixed> $baseline
     * @return array<string, mixed>
     */
    protected function evaluateMetrics(array $metrics, array $baseline): array
    {
        $evaluation = [];
        foreach ($baseline as $key => $spec) {
            $value = $metrics[$key] ?? null;
            if ($value === null || !is_numeric($value)) {
                $evaluation[$key] = ['status' => 'no_data', 'value' => $value];
                continue;
            }

            $status = 'ok';
            $value = (float) $value;
            $targetMin = isset($spec['target_min']) ? (float) $spec['target_min'] : null;
            $targetMax = isset($spec['target_max']) ? (float) $spec['target_max'] : null;
            $warningBelow = isset($spec['warning_below']) ? (float) $spec['warning_below'] : null;
            $warningAbove = isset($spec['warning_above']) ? (float) $spec['warning_above'] : null;

            if ($targetMin !== null && $value < $targetMin) {
                $status = 'breach';
            }
            if ($targetMax !== null && $value > $targetMax) {
                $status = 'breach';
            }
            if ($status !== 'breach') {
                if ($warningBelow !== null && $value < $warningBelow) {
                    $status = 'warning';
                }
                if ($warningAbove !== null && $value > $warningAbove) {
                    $status = 'warning';
                }
            }

            $evaluation[$key] = [
                'status' => $status,
                'value' => $value,
                'target_min' => $targetMin,
                'target_max' => $targetMax,
            ];
        }

        return $evaluation;
    }
}
