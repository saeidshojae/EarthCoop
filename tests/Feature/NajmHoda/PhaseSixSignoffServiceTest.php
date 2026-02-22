<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaContinuousEvaluationHarnessService;
use App\Services\NajmHoda\Runtime\NajmHodaOperationalAutonomyActivationService;
use App\Services\NajmHoda\Runtime\NajmHodaPhaseSixSignoffService;
use App\Services\NajmHoda\Runtime\NajmHodaProductionReadinessService;
use App\Services\NajmHoda\Runtime\NajmHodaSafeCodeOpsCanaryService;
use App\Services\NajmHoda\Runtime\NajmHodaShadowLiveRolloutService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PhaseSixSignoffServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.autonomy.phase6_signoff.require_autonomous_live_stage' => true,
        ]);
        Cache::flush();
    }

    public function test_report_is_conditional_when_rollout_not_final_stage(): void
    {
        $bus = new InMemoryRuntimeEventBus(300);

        $readiness = \Mockery::mock(NajmHodaProductionReadinessService::class);
        $readiness->shouldReceive('review')->once()->andReturn([
            'decision' => 'go',
            'blocker_count' => 0,
            'warning_count' => 0,
            'generated_at' => now()->toIso8601String(),
            'evidence' => ['integrity_hash' => 'abc'],
        ]);
        $rollout = \Mockery::mock(NajmHodaShadowLiveRolloutService::class);
        $rollout->shouldReceive('status')->once()->andReturn(['stage' => 'limited_live', 'last_decision' => 'promoted']);
        $rollout->shouldReceive('evaluate')->once()->andReturn(['report' => ['decision' => 'hold', 'can_advance' => true]]);
        $evaluation = \Mockery::mock(NajmHodaContinuousEvaluationHarnessService::class);
        $evaluation->shouldReceive('lastReport')->once()->andReturn(['status' => 'ok', 'alert_count' => 0, 'decision_quality' => ['score' => 0.9]]);
        $operations = \Mockery::mock(NajmHodaOperationalAutonomyActivationService::class);
        $operations->shouldReceive('status')->once()->andReturn(['status' => 'active', 'last_tick_status' => 'ok']);
        $codeOps = \Mockery::mock(NajmHodaSafeCodeOpsCanaryService::class);
        $codeOps->shouldReceive('status')->once()->andReturn(['status' => 'idle', 'phase_percent' => 25]);

        $service = new NajmHodaPhaseSixSignoffService($bus, $readiness, $rollout, $evaluation, $operations, $codeOps);
        $report = $service->report(24, true);

        $this->assertSame('conditional_go', (string) ($report['decision'] ?? ''));
        $this->assertContains('rollout_not_at_autonomous_live', (array) ($report['rationale'] ?? []));
    }

    public function test_sign_records_decision_and_state(): void
    {
        $bus = new InMemoryRuntimeEventBus(300);

        $readiness = \Mockery::mock(NajmHodaProductionReadinessService::class);
        $readiness->shouldReceive('review')->once()->andReturn([
            'decision' => 'go',
            'blocker_count' => 0,
            'warning_count' => 0,
            'generated_at' => now()->toIso8601String(),
            'evidence' => ['integrity_hash' => 'abc'],
        ]);
        $rollout = \Mockery::mock(NajmHodaShadowLiveRolloutService::class);
        $rollout->shouldReceive('status')->once()->andReturn(['stage' => 'autonomous_live', 'last_decision' => 'stable_live']);
        $rollout->shouldReceive('evaluate')->once()->andReturn(['report' => ['decision' => 'stable_live', 'can_advance' => false]]);
        $evaluation = \Mockery::mock(NajmHodaContinuousEvaluationHarnessService::class);
        $evaluation->shouldReceive('lastReport')->once()->andReturn(['status' => 'ok', 'alert_count' => 0, 'decision_quality' => ['score' => 0.95]]);
        $operations = \Mockery::mock(NajmHodaOperationalAutonomyActivationService::class);
        $operations->shouldReceive('status')->once()->andReturn(['status' => 'active', 'last_tick_status' => 'ok']);
        $codeOps = \Mockery::mock(NajmHodaSafeCodeOpsCanaryService::class);
        $codeOps->shouldReceive('status')->once()->andReturn(['status' => 'idle', 'phase_percent' => 100]);

        $service = new NajmHodaPhaseSixSignoffService($bus, $readiness, $rollout, $evaluation, $operations, $codeOps);
        $result = $service->sign('go', 77, 'executive approval', 24);

        $this->assertTrue((bool) ($result['success'] ?? false));
        $this->assertSame('go', (string) data_get($result, 'state.last_signed_decision', ''));
        $this->assertSame(77, (int) data_get($result, 'state.last_signed_by', 0));
    }
}
