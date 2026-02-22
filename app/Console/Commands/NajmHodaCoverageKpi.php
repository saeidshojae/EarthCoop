<?php

namespace App\Console\Commands;

use App\Services\NajmHoda\Runtime\NajmHodaCoverageProbeService;
use App\Services\NajmHoda\Runtime\NajmHodaCoverageHeartbeatService;
use App\Services\NajmHoda\Runtime\NajmHodaEventCoverageKpiService;
use Illuminate\Console\Command;

class NajmHodaCoverageKpi extends Command
{
    protected $signature = 'najm-hoda:coverage-kpi
        {--window= : Window in hours}
        {--limit= : Number of recent events to inspect}
        {--heartbeat : Emit in-process non-probe heartbeat events before KPI snapshot}
        {--probe : Emit in-process coverage probes before KPI snapshot}
        {--require-sustained : Fail when sustained KPI condition is not met}
        {--fail-on-breach : Return non-zero exit code when any KPI breaches threshold}';

    protected $description = 'Calculate Najm Hoda Phase-6 event coverage KPIs';

    public function __construct(
        protected NajmHodaEventCoverageKpiService $coverageKpiService,
        protected NajmHodaCoverageProbeService $probeService,
        protected NajmHodaCoverageHeartbeatService $heartbeatService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!(bool) config('najm-hoda.enabled', true)) {
            $this->warn('Najm Hoda is disabled. Coverage KPI run skipped.');
            return self::SUCCESS;
        }

        $window = $this->option('window');
        $limit = $this->option('limit');
        $heartbeat = (bool) $this->option('heartbeat');
        $probe = (bool) $this->option('probe');
        $windowHours = is_numeric($window) ? (int) $window : null;
        $eventLimit = is_numeric($limit) ? (int) $limit : null;

        if ($heartbeat) {
            $heartbeatRows = $this->heartbeatService->emit(false);
            $this->line('Coverage heartbeats emitted before snapshot.');
            $this->table(
                ['Domain', 'Event', 'Action'],
                array_map(static fn (array $item): array => [
                    (string) $item['domain'],
                    (string) $item['event'],
                    (string) $item['action'],
                ], $heartbeatRows)
            );
        }

        if ($probe) {
            $probeRows = $this->probeService->emit(false);
            $this->line('Coverage probes emitted before snapshot.');
            $this->table(
                ['Domain', 'Event', 'Action'],
                array_map(static fn (array $item): array => [
                    (string) $item['domain'],
                    (string) $item['event'],
                    (string) $item['action'],
                ], $probeRows)
            );
        }

        $snapshot = $this->coverageKpiService->snapshot($windowHours, $eventLimit, $probe);

        $metrics = is_array($snapshot['metrics'] ?? null) ? $snapshot['metrics'] : [];
        $evaluation = is_array($snapshot['evaluation'] ?? null) ? $snapshot['evaluation'] : [];
        $thresholds = is_array($snapshot['thresholds'] ?? null) ? $snapshot['thresholds'] : [];

        $this->line('Najm Hoda Coverage KPI Snapshot');
        $this->table(
            ['Metric', 'Value', 'Target', 'Status'],
            [
                [
                    'critical_path_coverage',
                    (string) ($metrics['critical_path_coverage'] ?? 0),
                    '>=' . (string) ($thresholds['critical_path_coverage_min'] ?? 0.95),
                    (string) ($evaluation['critical_path_coverage'] ?? 'unknown'),
                ],
                [
                    'mandatory_field_completeness',
                    (string) ($metrics['mandatory_field_completeness'] ?? 0),
                    '>=' . (string) ($thresholds['mandatory_field_completeness_min'] ?? 0.99),
                    (string) ($evaluation['mandatory_field_completeness'] ?? 'unknown'),
                ],
                [
                    'unknown_scope_ratio',
                    (string) ($metrics['unknown_scope_ratio'] ?? 0),
                    '<=' . (string) ($thresholds['unknown_scope_ratio_max'] ?? 0.02),
                    (string) ($evaluation['unknown_scope_ratio'] ?? 'unknown'),
                ],
                [
                    'unknown_risk_ratio',
                    (string) ($metrics['unknown_risk_ratio'] ?? 0),
                    '<=' . (string) ($thresholds['unknown_risk_ratio_max'] ?? 0.05),
                    (string) ($evaluation['unknown_risk_ratio'] ?? 'unknown'),
                ],
            ]
        );

        $this->line(sprintf(
            'Critical families observed: %d/%d',
            (int) data_get($metrics, 'counters.critical_observed_families', 0),
            (int) data_get($metrics, 'counters.critical_total_families', 0)
        ));

        $this->line(sprintf(
            'Sustainment: %d/%d consecutive ok snapshots (without_probe_only=%s) => %s',
            (int) data_get($snapshot, 'sustainment.consecutive_ok', 0),
            (int) data_get($snapshot, 'sustainment.required_consecutive_ok', 0),
            ((bool) data_get($snapshot, 'sustainment.considered_without_probe_only', true)) ? 'yes' : 'no',
            ((bool) data_get($snapshot, 'sustainment.sustained_ok', false)) ? 'ok' : 'not_met'
        ));

        $hasBreach = in_array('breach', array_values($evaluation), true);
        if ($hasBreach) {
            $this->warn('Coverage KPI contains breach status.');
            if ((bool) $this->option('fail-on-breach')) {
                return self::FAILURE;
            }
        }

        if ((bool) $this->option('require-sustained') && !(bool) data_get($snapshot, 'sustainment.sustained_ok', false)) {
            $this->warn('Coverage KPI sustainment requirement is not met.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
