<?php

namespace App\Services\NajmHoda\Runtime;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class NajmHodaEventCoverageKpiService
{
    public function __construct(
        protected RuntimeEventBus $eventBus
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(?int $windowHours = null, ?int $eventLimit = null, bool $probeUsed = false): array
    {
        $windowHours = $windowHours ?? (int) config('najm-hoda.runtime.coverage_kpi.window_hours', 24);
        $windowHours = max(1, $windowHours);

        $eventLimit = $eventLimit ?? (int) config('najm-hoda.runtime.coverage_kpi.event_limit', 5000);
        $eventLimit = max(100, $eventLimit);

        $events = $this->eventBus->recent(null, $eventLimit);
        $events = $this->filterByWindow($events, $windowHours);

        $metrics = $this->calculateMetrics($events);
        $thresholds = $this->thresholds();
        $evaluation = $this->evaluate($metrics, $thresholds);

        $snapshot = [
            'generated_at' => now()->toIso8601String(),
            'window_hours' => $windowHours,
            'event_limit' => $eventLimit,
            'probe_used' => $probeUsed,
            'event_count' => count($events),
            'metrics' => $metrics,
            'thresholds' => $thresholds,
            'evaluation' => $evaluation,
        ];

        $ttlMinutes = max(30, (int) config('najm-hoda.runtime.coverage_kpi.snapshot_ttl_minutes', 180));
        Cache::put('najm_hoda:coverage_kpi:last_snapshot', $snapshot, now()->addMinutes($ttlMinutes));
        $history = $this->recordHistory($snapshot, $ttlMinutes);
        $snapshot['history'] = $history;
        $snapshot['sustainment'] = $this->sustainment($history);

        $this->eventBus->emit('najm_hoda.autonomy.coverage_kpi.snapshot', [
            'critical_path_coverage' => (float) ($metrics['critical_path_coverage'] ?? 0.0),
            'mandatory_field_completeness' => (float) ($metrics['mandatory_field_completeness'] ?? 0.0),
            'unknown_scope_ratio' => (float) ($metrics['unknown_scope_ratio'] ?? 0.0),
            'unknown_risk_ratio' => (float) ($metrics['unknown_risk_ratio'] ?? 0.0),
            'critical_observed_families' => (int) data_get($metrics, 'counters.critical_observed_families', 0),
            'critical_total_families' => (int) data_get($metrics, 'counters.critical_total_families', 0),
            'probe_used' => $probeUsed,
            'sustained_ok' => (bool) data_get($snapshot, 'sustainment.sustained_ok', false),
        ]);

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
                return CarbonImmutable::parse($timestamp)->greaterThanOrEqualTo($cutoff);
            } catch (\Throwable) {
                return false;
            }
        }));
    }

    /**
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    protected function calculateMetrics(array $events): array
    {
        $criticalFamilies = $this->criticalFamilies();
        $mandatoryFields = $this->mandatoryFields();
        $unknownScopes = $this->unknownScopes();
        $unknownRisks = $this->unknownRisks();

        $observedFamilies = [];
        $criticalEvents = 0;
        $mandatoryOk = 0;
        $unknownScopeCount = 0;
        $unknownRiskCount = 0;

        foreach ($events as $entry) {
            $event = (string) ($entry['event'] ?? '');
            $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];

            foreach ($criticalFamilies as $family) {
                if ($family !== '' && str_starts_with($event, $family)) {
                    $observedFamilies[$family] = true;
                    $criticalEvents++;

                    if ($this->hasMandatoryFields($payload, $mandatoryFields)) {
                        $mandatoryOk++;
                    }

                    $scope = strtolower(trim((string) ($payload['scope'] ?? '')));
                    if ($scope === '' || in_array($scope, $unknownScopes, true)) {
                        $unknownScopeCount++;
                    }

                    $risk = strtolower(trim((string) ($payload['risk'] ?? 'unknown')));
                    if ($risk === '' || in_array($risk, $unknownRisks, true)) {
                        $unknownRiskCount++;
                    }

                    break;
                }
            }
        }

        $familyTotal = max(1, count($criticalFamilies));
        $criticalPathCoverage = round(count($observedFamilies) / $familyTotal, 4);
        $den = max(1, $criticalEvents);
        $mandatoryFieldCompleteness = round($mandatoryOk / $den, 4);
        $unknownScopeRatio = round($unknownScopeCount / $den, 4);
        $unknownRiskRatio = round($unknownRiskCount / $den, 4);

        return [
            'critical_path_coverage' => $criticalPathCoverage,
            'mandatory_field_completeness' => $mandatoryFieldCompleteness,
            'unknown_scope_ratio' => $unknownScopeRatio,
            'unknown_risk_ratio' => $unknownRiskRatio,
            'counters' => [
                'critical_total_families' => count($criticalFamilies),
                'critical_observed_families' => count($observedFamilies),
                'critical_event_count' => $criticalEvents,
                'mandatory_ok_count' => $mandatoryOk,
                'unknown_scope_count' => $unknownScopeCount,
                'unknown_risk_count' => $unknownRiskCount,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $mandatoryFields
     */
    protected function hasMandatoryFields(array $payload, array $mandatoryFields): bool
    {
        foreach ($mandatoryFields as $field) {
            if (!array_key_exists($field, $payload)) {
                return false;
            }

            $value = $payload[$field];
            if ($value === null) {
                return false;
            }
            if (is_string($value) && trim($value) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, float>
     */
    protected function thresholds(): array
    {
        return [
            'critical_path_coverage_min' => (float) config('najm-hoda.runtime.coverage_kpi.thresholds.critical_path_coverage_min', 0.95),
            'mandatory_field_completeness_min' => (float) config('najm-hoda.runtime.coverage_kpi.thresholds.mandatory_field_completeness_min', 0.99),
            'unknown_scope_ratio_max' => (float) config('najm-hoda.runtime.coverage_kpi.thresholds.unknown_scope_ratio_max', 0.02),
            'unknown_risk_ratio_max' => (float) config('najm-hoda.runtime.coverage_kpi.thresholds.unknown_risk_ratio_max', 0.05),
        ];
    }

    /**
     * @param array<string, mixed> $metrics
     * @param array<string, float> $thresholds
     * @return array<string, string>
     */
    protected function evaluate(array $metrics, array $thresholds): array
    {
        return [
            'critical_path_coverage' => ((float) ($metrics['critical_path_coverage'] ?? 0.0)) >= $thresholds['critical_path_coverage_min'] ? 'ok' : 'breach',
            'mandatory_field_completeness' => ((float) ($metrics['mandatory_field_completeness'] ?? 0.0)) >= $thresholds['mandatory_field_completeness_min'] ? 'ok' : 'breach',
            'unknown_scope_ratio' => ((float) ($metrics['unknown_scope_ratio'] ?? 1.0)) <= $thresholds['unknown_scope_ratio_max'] ? 'ok' : 'breach',
            'unknown_risk_ratio' => ((float) ($metrics['unknown_risk_ratio'] ?? 1.0)) <= $thresholds['unknown_risk_ratio_max'] ? 'ok' : 'breach',
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function criticalFamilies(): array
    {
        $families = config('najm-hoda.runtime.coverage_kpi.critical_families', []);
        if (!is_array($families)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            $families
        )));
    }

    /**
     * @return array<int, string>
     */
    protected function mandatoryFields(): array
    {
        $fields = config('najm-hoda.runtime.coverage_kpi.mandatory_fields', []);
        if (!is_array($fields)) {
            return ['request_id', 'correlation_id', 'actor_id', 'scope', 'risk', 'event_version', 'emitted_at'];
        }

        return array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            $fields
        )));
    }

    /**
     * @return array<int, string>
     */
    protected function unknownScopes(): array
    {
        $scopes = config('najm-hoda.runtime.coverage_kpi.unknown_scopes', ['unknown', 'global']);
        if (!is_array($scopes)) {
            return ['unknown', 'global'];
        }

        return array_values(array_unique(array_map(
            static fn ($value): string => strtolower(trim((string) $value)),
            $scopes
        )));
    }

    /**
     * @return array<int, string>
     */
    protected function unknownRisks(): array
    {
        $risks = config('najm-hoda.runtime.coverage_kpi.unknown_risks', ['unknown']);
        if (!is_array($risks)) {
            return ['unknown'];
        }

        return array_values(array_unique(array_map(
            static fn ($value): string => strtolower(trim((string) $value)),
            $risks
        )));
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<int, array<string, mixed>>
     */
    protected function recordHistory(array $snapshot, int $ttlMinutes): array
    {
        $key = 'najm_hoda:coverage_kpi:history';
        $history = Cache::get($key, []);
        if (!is_array($history)) {
            $history = [];
        }

        array_unshift($history, [
            'generated_at' => (string) ($snapshot['generated_at'] ?? now()->toIso8601String()),
            'probe_used' => (bool) ($snapshot['probe_used'] ?? false),
            'evaluation' => is_array($snapshot['evaluation'] ?? null) ? $snapshot['evaluation'] : [],
            'metrics' => is_array($snapshot['metrics'] ?? null) ? $snapshot['metrics'] : [],
        ]);

        $historySize = max(10, (int) config('najm-hoda.runtime.coverage_kpi.history_size', 200));
        $history = array_slice($history, 0, $historySize);
        Cache::put($key, $history, now()->addMinutes($ttlMinutes));

        return $history;
    }

    /**
     * @param array<int, array<string, mixed>> $history
     * @return array<string, mixed>
     */
    protected function sustainment(array $history): array
    {
        $required = max(1, (int) config('najm-hoda.runtime.coverage_kpi.sustainment.required_consecutive_ok', 3));
        $withoutProbe = (bool) config('najm-hoda.runtime.coverage_kpi.sustainment.require_without_probe', true);

        $considered = [];
        foreach ($history as $entry) {
            $probeUsed = (bool) ($entry['probe_used'] ?? false);
            if ($withoutProbe && $probeUsed) {
                continue;
            }
            $considered[] = $entry;
            if (count($considered) >= $required) {
                break;
            }
        }

        $consecutiveOk = 0;
        foreach ($considered as $entry) {
            $evaluation = is_array($entry['evaluation'] ?? null) ? $entry['evaluation'] : [];
            $allOk = !empty($evaluation) && !in_array('breach', array_values($evaluation), true);
            if ($allOk) {
                $consecutiveOk++;
                continue;
            }
            break;
        }

        return [
            'required_consecutive_ok' => $required,
            'considered_without_probe_only' => $withoutProbe,
            'considered_snapshots' => count($considered),
            'consecutive_ok' => $consecutiveOk,
            'sustained_ok' => $consecutiveOk >= $required,
        ];
    }
}
