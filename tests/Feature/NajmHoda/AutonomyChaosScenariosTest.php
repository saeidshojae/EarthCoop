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

class AutonomyChaosScenariosTest extends TestCase
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
            'najm-hoda.runtime.autonomy.executor.enabled' => true,
            'najm-hoda.runtime.autonomy.executor.max_retries' => 1,
            'najm-hoda.runtime.autonomy.executor.idempotency_ttl_minutes' => 1,
            'najm-hoda.runtime.autonomy.executor.default_action_cooldown_seconds' => 0,
        ]);

        Cache::flush();
    }

    public function test_chaos_executor_retry_exhaustion_records_failed_result(): void
    {
        $bus = new InMemoryRuntimeEventBus(200);
        $executor = new NajmHodaOperatorActionExecutorV2($bus);

        $result = $executor->execute([[
            'action' => 'unknown_chaos_action',
            'mode' => 'apply',
            'risk' => 'low',
            'input' => ['seed' => 1],
        ]], 'chaos-run-1');

        $this->assertSame('failed', (string) data_get($result, '0.status', ''));
        $this->assertSame(2, (int) data_get($result, '0.attempt', 0));

        $failedEvent = $bus->recent('najm_hoda.autonomy.executor.failed', 1);
        $this->assertNotEmpty($failedEvent);
    }

    public function test_chaos_replay_consistency_keeps_plan_and_execution_shape(): void
    {
        $bus = new InMemoryRuntimeEventBus(300);

        $loop = $this->makeGoalLoop($bus);
        $result = $loop->run(['improve_user_experience'], true, 80);

        $runId = (string) ($result['run_id'] ?? '');
        $this->assertNotSame('', $runId);

        $audit = new NajmHodaAutonomyAuditService($bus);
        $replay = $audit->replay($runId);

        $this->assertTrue((bool) ($replay['success'] ?? false));
        $this->assertSame($runId, (string) ($replay['run_id'] ?? ''));
        $this->assertCount(count((array) ($result['plan'] ?? [])), (array) ($replay['plan'] ?? []));
        $this->assertCount(count((array) ($result['execution_results'] ?? [])), (array) ($replay['execution_results'] ?? []));
    }

    protected function makeGoalLoop(InMemoryRuntimeEventBus $bus): NajmHodaAutonomousGoalLoopService
    {
        $observability = new NajmHodaObservabilityGraphService($bus);
        $recommendations = new NajmHodaProactiveRecommendationService($bus);
        $executor = new NajmHodaOperatorActionExecutorV2($bus);
        $control = new NajmHodaAutonomyControlService($bus);
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
