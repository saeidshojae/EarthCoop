<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaContinuousEvaluationHarnessService;
use App\Services\NajmHoda\Runtime\NajmHodaGovernanceAlertingService;
use App\Services\NajmHoda\Runtime\NajmHodaOperationalAutonomyActivationService;
use App\Services\NajmHoda\Runtime\NajmHodaSafeCodeOpsCanaryService;
use App\Services\NajmHoda\Runtime\NajmHodaShadowLiveRolloutService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ShadowLiveRolloutServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.autonomy.shadow_rollout.enabled' => true,
            'najm-hoda.runtime.autonomy.shadow_rollout.initial_stage' => 'shadow',
            'najm-hoda.runtime.autonomy.shadow_rollout.stages.shadow.decision_quality_min' => 0.60,
            'najm-hoda.runtime.autonomy.shadow_rollout.stages.shadow.max_critical_alerts' => 0,
            'najm-hoda.runtime.autonomy.shadow_rollout.stages.shadow.max_total_alerts' => 1,
            'najm-hoda.runtime.autonomy.shadow_rollout.stages.shadow.allow_non_active_ops' => false,
        ]);
        Cache::flush();
    }

    public function test_advance_succeeds_when_guardrails_pass(): void
    {
        $bus = new InMemoryRuntimeEventBus(300);

        $evaluation = \Mockery::mock(NajmHodaContinuousEvaluationHarnessService::class);
        $evaluation->shouldReceive('run')->once()->andReturn([
            'status' => 'ok',
            'decision_quality' => ['score' => 0.90],
        ]);

        $codeOps = \Mockery::mock(NajmHodaSafeCodeOpsCanaryService::class);
        $codeOps->shouldReceive('status')->once()->andReturn([
            'status' => 'idle',
        ]);

        $operations = \Mockery::mock(NajmHodaOperationalAutonomyActivationService::class);
        $operations->shouldReceive('status')->once()->andReturn([
            'status' => 'active',
        ]);

        $alerts = \Mockery::mock(NajmHodaGovernanceAlertingService::class);
        $alerts->shouldReceive('evaluateAndAlert')->once()->andReturn([
            'count' => 0,
            'alerts' => [],
        ]);

        $service = new NajmHodaShadowLiveRolloutService(
            $bus,
            $evaluation,
            $codeOps,
            $operations,
            $alerts
        );

        $result = $service->advance(99, 'test_promotion');
        $this->assertTrue((bool) ($result['success'] ?? false));
        $this->assertSame('limited_live', (string) data_get($result, 'state.stage', 'shadow'));
        $this->assertSame('promoted', (string) data_get($result, 'state.last_decision', ''));
    }

    public function test_advance_is_blocked_when_guardrails_fail(): void
    {
        $bus = new InMemoryRuntimeEventBus(300);

        $evaluation = \Mockery::mock(NajmHodaContinuousEvaluationHarnessService::class);
        $evaluation->shouldReceive('run')->once()->andReturn([
            'status' => 'breach',
            'decision_quality' => ['score' => 0.50],
        ]);

        $codeOps = \Mockery::mock(NajmHodaSafeCodeOpsCanaryService::class);
        $codeOps->shouldReceive('status')->once()->andReturn([
            'status' => 'rolled_back',
        ]);

        $operations = \Mockery::mock(NajmHodaOperationalAutonomyActivationService::class);
        $operations->shouldReceive('status')->once()->andReturn([
            'status' => 'halted',
        ]);

        $alerts = \Mockery::mock(NajmHodaGovernanceAlertingService::class);
        $alerts->shouldReceive('evaluateAndAlert')->once()->andReturn([
            'count' => 2,
            'alerts' => [
                ['severity' => 'critical'],
                ['severity' => 'warning'],
            ],
        ]);

        $service = new NajmHodaShadowLiveRolloutService(
            $bus,
            $evaluation,
            $codeOps,
            $operations,
            $alerts
        );

        $result = $service->advance(99, 'test_blocked');
        $this->assertFalse((bool) ($result['success'] ?? true));
        $this->assertSame('guardrails_not_passed', (string) ($result['reason'] ?? ''));
        $this->assertContains('evaluation_breach', (array) data_get($result, 'report.blocking_reasons', []));
    }
}
