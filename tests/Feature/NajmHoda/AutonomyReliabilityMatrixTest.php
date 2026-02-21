<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyAuditService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyControlService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomySafetyGate;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomousGoalLoopService;
use App\Services\NajmHoda\Runtime\NajmHodaCapabilityRegistry;
use App\Services\NajmHoda\Runtime\NajmHodaObservabilityGraphService;
use App\Services\NajmHoda\Runtime\NajmHodaOperatorActionExecutorV2;
use App\Services\NajmHoda\Runtime\NajmHodaProactiveRecommendationService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AutonomyReliabilityMatrixTest extends TestCase
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
            'najm-hoda.runtime.autonomy.recommendations.enabled' => true,
            'najm-hoda.runtime.autonomy.executor.enabled' => true,
            'najm-hoda.runtime.autonomy.executor.max_retries' => 1,
            'najm-hoda.runtime.autonomy.capabilities.run_ops_monitor.required_input' => ['health_status'],
            'najm-hoda.runtime.autonomy.capabilities.propose_engagement_recommendations.required_input' => ['goal_count'],
        ]);

        Cache::flush();
    }

    public function test_reliability_matrix_paused_state_halts_execution_and_records_audit(): void
    {
        $bus = new InMemoryRuntimeEventBus(300);
        $control = new NajmHodaAutonomyControlService($bus);
        $control->pause(1, 'maintenance', 10);

        $service = $this->makeGoalLoop($bus, $control);
        $result = $service->run(['stabilize_operations'], true, 100);

        $this->assertFalse((bool) ($result['executed'] ?? true));
        $this->assertSame('paused', (string) ($result['status'] ?? ''));

        $audit = new NajmHodaAutonomyAuditService($bus);
        $history = $audit->history(5);
        $this->assertNotEmpty($history);
        $this->assertSame('paused', (string) ($history[0]['status'] ?? ''));
    }

    public function test_reliability_matrix_force_propose_override_prevents_apply_execution(): void
    {
        $bus = new InMemoryRuntimeEventBus(300);
        $control = new NajmHodaAutonomyControlService($bus);
        $control->setOverride('propose', [], null, 1, 'safety hold');

        $service = $this->makeGoalLoop($bus, $control);
        $result = $service->run(['improve_user_experience'], true, 100);

        $this->assertTrue((bool) ($result['executed'] ?? false));
        $this->assertSame('propose', (string) data_get($result, 'plan.0.mode', ''));
        $this->assertSame('skipped', (string) data_get($result, 'execution_results.0.status', ''));
        $this->assertSame('mode_not_apply', (string) data_get($result, 'execution_results.0.reason', ''));
    }

    protected function makeGoalLoop(InMemoryRuntimeEventBus $bus, NajmHodaAutonomyControlService $control): NajmHodaAutonomousGoalLoopService
    {
        $observability = new NajmHodaObservabilityGraphService($bus);
        $recommendations = new NajmHodaProactiveRecommendationService($bus);
        $executor = new NajmHodaOperatorActionExecutorV2($bus);
        $audit = new NajmHodaAutonomyAuditService($bus);
        $registry = new NajmHodaCapabilityRegistry($bus);
        $safety = new NajmHodaAutonomySafetyGate($bus);
        $approval = new NajmHodaAutonomyApprovalService($bus, app(NotificationService::class));

        return new NajmHodaAutonomousGoalLoopService(
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
    }
}
