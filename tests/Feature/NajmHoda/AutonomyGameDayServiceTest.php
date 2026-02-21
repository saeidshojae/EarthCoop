<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyAuditService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyControlService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyGameDayService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomySafetyGate;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomousGoalLoopService;
use App\Services\NajmHoda\Runtime\NajmHodaCapabilityRegistry;
use App\Services\NajmHoda\Runtime\NajmHodaGovernanceAlertingService;
use App\Services\NajmHoda\Runtime\NajmHodaGovernanceKpiCatalogService;
use App\Services\NajmHoda\Runtime\NajmHodaGovernanceMetricsAggregatorService;
use App\Services\NajmHoda\Runtime\NajmHodaObservabilityGraphService;
use App\Services\NajmHoda\Runtime\NajmHodaOperatorActionExecutorV2;
use App\Services\NajmHoda\Runtime\NajmHodaProactiveRecommendationService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AutonomyGameDayServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'najm-hoda.enabled' => true,
            'najm-hoda.runtime.autonomy.enabled' => true,
            'najm-hoda.runtime.autonomy.allow_apply_low_risk' => true,
            'najm-hoda.runtime.autonomy.human_escalation.enabled' => false,
            'najm-hoda.runtime.autonomy.governance.alerting.enabled' => true,
            'najm-hoda.runtime.autonomy.governance.alerting.notify_admins' => false,
            'najm-hoda.runtime.autonomy.gameday.history_size' => 20,
            'najm-hoda.runtime.autonomy.gameday.report_ttl_minutes' => 180,
        ]);

        Cache::flush();
    }

    public function test_gameday_runs_core_scenarios_and_persists_report_history(): void
    {
        $bus = new InMemoryRuntimeEventBus(600);
        $control = new NajmHodaAutonomyControlService($bus);
        $audit = new NajmHodaAutonomyAuditService($bus);
        $registry = new NajmHodaCapabilityRegistry($bus);
        $safety = new NajmHodaAutonomySafetyGate($bus);
        $observability = new NajmHodaObservabilityGraphService($bus);
        $recommendations = new NajmHodaProactiveRecommendationService($bus);
        $executor = new NajmHodaOperatorActionExecutorV2($bus, null, $control);
        $approval = new NajmHodaAutonomyApprovalService($bus, app(NotificationService::class));
        $goalLoop = new NajmHodaAutonomousGoalLoopService(
            $bus,
            $observability,
            $recommendations,
            $executor,
            $control,
            $audit,
            $registry,
            $safety,
            $approval
        );

        $metrics = new NajmHodaGovernanceMetricsAggregatorService($bus, new NajmHodaGovernanceKpiCatalogService());
        $alerting = new NajmHodaGovernanceAlertingService($bus, $metrics, $approval, app(NotificationService::class));
        $service = new NajmHodaAutonomyGameDayService($bus, $control, $goalLoop, $audit, $alerting);

        $report = $service->run([], true);

        $this->assertSame('pass', (string) ($report['status'] ?? 'fail'));
        $this->assertSame(4, (int) ($report['scenario_count'] ?? 0));
        $this->assertGreaterThanOrEqual(4, (int) ($report['passed_count'] ?? 0));

        $history = $service->history(10);
        $this->assertNotEmpty($history);
        $this->assertSame((string) ($report['generated_at'] ?? ''), (string) ($history[0]['generated_at'] ?? ''));

        $event = $bus->recent('najm_hoda.autonomy.gameday.completed', 1);
        $this->assertNotEmpty($event);
    }
}
