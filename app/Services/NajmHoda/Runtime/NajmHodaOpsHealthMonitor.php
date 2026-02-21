<?php

namespace App\Services\NajmHoda\Runtime;

use Carbon\CarbonImmutable;

class NajmHodaOpsHealthMonitor
{
    public function __construct(
        protected RuntimeEventBus $eventBus
    ) {
    }

    public function snapshot(?int $windowMinutes = null, ?int $limit = null): array
    {
        $windowMinutes = $windowMinutes ?? (int) config('najm-hoda.runtime.ops.monitor.window_minutes', 15);
        $limit = $limit ?? (int) config('najm-hoda.runtime.ops.monitor.recent_limit', 400);

        $windowMinutes = max(1, $windowMinutes);
        $limit = max(50, $limit);

        $now = CarbonImmutable::now();
        $windowStart = $now->subMinutes($windowMinutes);

        $recentEvents = $this->eventBus->recent(null, $limit);
        $eventsInWindow = array_values(array_filter($recentEvents, static function (array $event) use ($windowStart): bool {
            $timestamp = $event['timestamp'] ?? null;
            if (!is_string($timestamp) || trim($timestamp) === '') {
                return false;
            }

            try {
                return CarbonImmutable::parse($timestamp)->greaterThanOrEqualTo($windowStart);
            } catch (\Throwable) {
                return false;
            }
        }));

        $counts = $this->countEvents($eventsInWindow);
        $received = $counts['request_received'];
        $ready = $counts['response_ready'];
        $failed = $counts['response_failed'];
        $resolved = $ready + $failed;

        $errorRate = $resolved > 0 ? round(($failed / $resolved) * 100, 2) : 0.0;
        $unresolved = max(0, $received - $resolved);
        $status = $this->resolveStatus($errorRate, $unresolved);

        $snapshot = [
            'status' => $status,
            'window_minutes' => $windowMinutes,
            'window_start' => $windowStart->toIso8601String(),
            'generated_at' => $now->toIso8601String(),
            'counts' => $counts,
            'metrics' => [
                'error_rate_percent' => $errorRate,
                'unresolved_requests' => $unresolved,
            ],
        ];

        $this->eventBus->emit('najm_hoda.ops.health.snapshot', [
            'status' => $status,
            'window_minutes' => $windowMinutes,
            'error_rate_percent' => $errorRate,
            'unresolved_requests' => $unresolved,
            'counts' => $counts,
        ]);

        return $snapshot;
    }

    /**
     * @param array<int, array<string, mixed>> $events
     * @return array<string, int>
     */
    protected function countEvents(array $events): array
    {
        $counts = [
            'total' => count($events),
            'request_received' => 0,
            'response_ready' => 0,
            'response_failed' => 0,
            'intent_detected' => 0,
            'ops_incidents' => 0,
        ];

        foreach ($events as $event) {
            $name = (string) ($event['event'] ?? '');
            switch ($name) {
                case 'najm_hoda.request.received':
                    $counts['request_received']++;
                    break;
                case 'najm_hoda.response.ready':
                    $counts['response_ready']++;
                    break;
                case 'najm_hoda.response.failed':
                    $counts['response_failed']++;
                    break;
                case 'najm_hoda.intent.detected':
                    $counts['intent_detected']++;
                    break;
                case 'najm_hoda.ops.incident.detected':
                    $counts['ops_incidents']++;
                    break;
            }
        }

        return $counts;
    }

    protected function resolveStatus(float $errorRatePercent, int $unresolvedRequests): string
    {
        $criticalErrorRate = (float) config('najm-hoda.runtime.ops.thresholds.critical_error_rate_percent', 35);
        $warningErrorRate = (float) config('najm-hoda.runtime.ops.thresholds.warning_error_rate_percent', 15);
        $criticalUnresolved = (int) config('najm-hoda.runtime.ops.thresholds.critical_unresolved_requests', 10);
        $warningUnresolved = (int) config('najm-hoda.runtime.ops.thresholds.warning_unresolved_requests', 4);

        if ($errorRatePercent >= $criticalErrorRate || $unresolvedRequests >= $criticalUnresolved) {
            return 'critical';
        }

        if ($errorRatePercent >= $warningErrorRate || $unresolvedRequests >= $warningUnresolved) {
            return 'warning';
        }

        return 'healthy';
    }
}

