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
}
