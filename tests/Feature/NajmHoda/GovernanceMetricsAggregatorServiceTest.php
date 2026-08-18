<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaGovernanceKpiCatalogService;
use App\Services\NajmHoda\Runtime\NajmHodaGovernanceMetricsAggregatorService;
use Tests\TestCase;

class GovernanceMetricsAggregatorServiceTest extends TestCase
{
    public function test_governance_snapshot_calculates_metrics_and_evaluation(): void
    {
        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.autonomy.governance.window_hours' => 24,
            'najm-hoda.runtime.autonomy.governance.event_limit' => 500,
        ]);

        $bus = new InMemoryRuntimeEventBus(500);
        $bus->emit('najm_hoda.autonomy.executor.executed', ['action' => 'run_ops_monitor']);
        $bus->emit('najm_hoda.autonomy.executor.executed', ['action' => 'run_ops_monitor']);
        $bus->emit('najm_hoda.autonomy.executor.failed', ['action' => 'run_ops_monitor']);
        $bus->emit('najm_hoda.autonomy.executor.skipped', ['action' => 'run_ops_monitor']);
        $bus->emit('najm_hoda.autonomy.contract.rejected', ['action' => 'x']);

        $service = new NajmHodaGovernanceMetricsAggregatorService(
            $bus,
            new NajmHodaGovernanceKpiCatalogService()
        );

        $snapshot = $service->snapshot(24);

        $this->assertIsArray($snapshot);
        $this->assertArrayHasKey('metrics', $snapshot);
        $this->assertArrayHasKey('evaluation', $snapshot);
        $this->assertSame(0.6667, (float) data_get($snapshot, 'metrics.auto_action_success_rate'));
        $this->assertSame(0.5, (float) data_get($snapshot, 'metrics.autonomy_coverage_rate'));
        $this->assertSame('breach', (string) data_get($snapshot, 'evaluation.auto_action_success_rate.status'));
    }

    public function test_missing_operational_samples_are_reported_as_no_data_not_breaches(): void
    {
        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.autonomy.governance.window_hours' => 24,
            'najm-hoda.runtime.autonomy.governance.event_limit' => 500,
        ]);

        $service = new NajmHodaGovernanceMetricsAggregatorService(
            new InMemoryRuntimeEventBus(500),
            new NajmHodaGovernanceKpiCatalogService()
        );

        $snapshot = $service->snapshot(24);

        $this->assertNull(data_get($snapshot, 'metrics.autonomy_coverage_rate'));
        $this->assertNull(data_get($snapshot, 'metrics.user_satisfaction_score'));
        $this->assertSame('no_data', (string) data_get($snapshot, 'evaluation.autonomy_coverage_rate.status'));
        $this->assertSame('no_data', (string) data_get($snapshot, 'evaluation.user_satisfaction_score.status'));
    }

    public function test_tagged_gameday_executor_events_do_not_pollute_operational_kpis(): void
    {
        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.autonomy.governance.window_hours' => 24,
            'najm-hoda.runtime.autonomy.governance.event_limit' => 500,
        ]);

        $bus = new InMemoryRuntimeEventBus(500);
        $bus->emit('najm_hoda.autonomy.executor.executed', [
            'run_id' => 'operational-run',
            'action' => 'run_ops_monitor',
        ]);
        $bus->emit('najm_hoda.autonomy.executor.skipped', [
            'run_id' => 'gameday-run',
            'action' => 'propose_engagement_recommendations',
            'reason' => 'mode_not_apply',
        ]);
        $bus->emit('najm_hoda.autonomy.gameday.run_tagged', [
            'run_id' => 'gameday-run',
            'scenario' => 'replay_consistency',
        ]);

        $service = new NajmHodaGovernanceMetricsAggregatorService(
            $bus,
            new NajmHodaGovernanceKpiCatalogService()
        );

        $snapshot = $service->snapshot(24);

        $this->assertSame(1.0, (float) data_get($snapshot, 'metrics.auto_action_success_rate'));
        $this->assertSame(1.0, (float) data_get($snapshot, 'metrics.autonomy_coverage_rate'));
        $this->assertSame(1, (int) data_get($snapshot, 'metrics.counters.executed'));
        $this->assertSame(0, (int) data_get($snapshot, 'metrics.counters.skipped'));
        $this->assertSame(1, (int) data_get($snapshot, 'metrics.counters.excluded_gameday_runs'));
    }
}
