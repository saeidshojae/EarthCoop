<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use App\Services\NajmHoda\Runtime\NajmHodaGovernanceAlertingService;
use App\Services\NajmHoda\Runtime\NajmHodaGovernanceKpiCatalogService;
use App\Services\NajmHoda\Runtime\NajmHodaGovernanceMetricsAggregatorService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GovernanceAlertingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.autonomy.governance.alerting.enabled' => true,
            'najm-hoda.runtime.autonomy.governance.alerting.notify_admins' => false,
            'najm-hoda.runtime.autonomy.governance.alerting.cooldown_minutes' => 1,
            'najm-hoda.runtime.autonomy.governance.alerting.max_alerts_per_run' => 20,
            'najm-hoda.runtime.autonomy.governance.alerting.approval_sla_overdue_threshold' => 1,
        ]);
        Cache::flush();
    }

    public function test_alerting_raises_kpi_and_sla_alerts_and_stores_history(): void
    {
        $bus = new InMemoryRuntimeEventBus(500);
        $bus->emit('najm_hoda.autonomy.executor.executed', ['action' => 'run_ops_monitor']);
        $bus->emit('najm_hoda.autonomy.executor.failed', ['action' => 'run_ops_monitor']);
        $bus->emit('najm_hoda.autonomy.executor.failed', ['action' => 'run_ops_monitor']);

        Cache::put('najm_hoda:autonomy:approval:requests', [[
            'id' => 'req-1',
            'status' => 'pending',
            'action' => 'run_ops_monitor',
            'risk' => 'high',
            'mode' => 'apply',
            'requested_at' => now()->subHours(2)->toIso8601String(),
            'deadline_at' => now()->subMinutes(20)->toIso8601String(),
        ]], now()->addHours(1));

        $aggregator = new NajmHodaGovernanceMetricsAggregatorService(
            $bus,
            new NajmHodaGovernanceKpiCatalogService()
        );
        $approval = new NajmHodaAutonomyApprovalService($bus, app(NotificationService::class));
        $service = new NajmHodaGovernanceAlertingService(
            $bus,
            $aggregator,
            $approval,
            app(NotificationService::class)
        );

        $result = $service->evaluateAndAlert(24, false);

        $this->assertGreaterThan(0, (int) ($result['count'] ?? 0));
        $codes = array_map(static fn (array $item): string => (string) ($item['code'] ?? ''), (array) ($result['alerts'] ?? []));
        $this->assertContains('GOV_APPROVAL_SLA_OVERDUE', $codes);

        $history = $service->history(20);
        $this->assertNotEmpty($history);

        $events = $bus->recent('najm_hoda.autonomy.governance.alert.raised', 10);
        $this->assertNotEmpty($events);
    }
}
