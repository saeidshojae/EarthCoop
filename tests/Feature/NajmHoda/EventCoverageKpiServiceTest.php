<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaEventCoverageKpiService;
use Tests\TestCase;

class EventCoverageKpiServiceTest extends TestCase
{
    public function test_snapshot_calculates_coverage_and_completeness_metrics(): void
    {
        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.coverage_kpi.critical_families' => [
                'najm_hoda.input.support.service.',
                'najm_hoda.input.auth.service.',
                'najm_hoda.input.content.service.',
            ],
            'najm-hoda.runtime.coverage_kpi.unknown_scopes' => ['unknown', 'global'],
            'najm-hoda.runtime.coverage_kpi.unknown_risks' => ['unknown'],
        ]);

        $bus = new InMemoryRuntimeEventBus(200);
        $bus->emit('najm_hoda.input.support.service.ticket_triage.succeeded', [
            'scope' => 'support:tickets',
            'risk' => 'low',
        ]);
        $bus->emit('najm_hoda.input.auth.service.login.succeeded', [
            'scope' => 'auth',
            'risk' => 'low',
        ]);

        $service = new NajmHodaEventCoverageKpiService($bus);
        $snapshot = $service->snapshot(24, 200);

        $this->assertSame(0.6667, (float) data_get($snapshot, 'metrics.critical_path_coverage'));
        $this->assertSame(1.0, (float) data_get($snapshot, 'metrics.mandatory_field_completeness'));
        $this->assertSame(0.0, (float) data_get($snapshot, 'metrics.unknown_scope_ratio'));
        $this->assertSame(0.0, (float) data_get($snapshot, 'metrics.unknown_risk_ratio'));
    }

    public function test_snapshot_tracks_unknown_scope_and_risk_ratios(): void
    {
        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.coverage_kpi.critical_families' => [
                'najm_hoda.input.content.service.',
            ],
            'najm-hoda.runtime.coverage_kpi.unknown_scopes' => ['unknown', 'global'],
            'najm-hoda.runtime.coverage_kpi.unknown_risks' => ['unknown'],
        ]);

        $bus = new InMemoryRuntimeEventBus(200);
        $bus->emit('najm_hoda.input.content.service.page.store.succeeded', [
            'scope' => 'global',
            'risk' => 'unknown',
        ]);

        $service = new NajmHodaEventCoverageKpiService($bus);
        $snapshot = $service->snapshot(24, 200);

        $this->assertSame(1.0, (float) data_get($snapshot, 'metrics.critical_path_coverage'));
        $this->assertSame(1.0, (float) data_get($snapshot, 'metrics.unknown_scope_ratio'));
        $this->assertSame(1.0, (float) data_get($snapshot, 'metrics.unknown_risk_ratio'));
        $this->assertSame('breach', (string) data_get($snapshot, 'evaluation.unknown_scope_ratio'));
    }

    public function test_sustainment_counts_recent_non_probe_ok_snapshots(): void
    {
        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.coverage_kpi.critical_families' => [
                'najm_hoda.input.support.service.',
            ],
            'najm-hoda.runtime.coverage_kpi.sustainment.required_consecutive_ok' => 2,
            'najm-hoda.runtime.coverage_kpi.sustainment.require_without_probe' => true,
        ]);

        $bus = new InMemoryRuntimeEventBus(400);
        $service = new NajmHodaEventCoverageKpiService($bus);

        $bus->emit('najm_hoda.input.support.service.coverage_probe.succeeded', [
            'scope' => 'support',
            'risk' => 'low',
        ]);
        $service->snapshot(24, 400, true);

        $bus->emit('najm_hoda.input.support.service.ticket_triage.succeeded', [
            'scope' => 'support',
            'risk' => 'low',
        ]);
        $service->snapshot(24, 400, false);
        $snapshot = $service->snapshot(24, 400, false);

        $this->assertSame(2, (int) data_get($snapshot, 'sustainment.required_consecutive_ok'));
        $this->assertSame(2, (int) data_get($snapshot, 'sustainment.consecutive_ok'));
        $this->assertTrue((bool) data_get($snapshot, 'sustainment.sustained_ok'));
    }
}
