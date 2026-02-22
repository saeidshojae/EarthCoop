<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyControlService;
use App\Services\NajmHoda\Runtime\NajmHodaDecisionPolicyDriftService;
use App\Services\NajmHoda\Runtime\NajmHodaGovernanceMetricsAggregatorService;
use App\Services\NajmHoda\Runtime\NajmHodaSafeCodeOpsCanaryService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SafeCodeOpsCanaryServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::flush();
    }

    public function test_start_and_promote_canary_when_health_is_ok(): void
    {
        $bus = new InMemoryRuntimeEventBus(300);
        $metrics = \Mockery::mock(NajmHodaGovernanceMetricsAggregatorService::class);
        $metrics->shouldReceive('snapshot')->twice()->andReturn([
            'event_count' => 10,
            'metrics' => ['auto_action_success_rate' => 0.97],
            'evaluation' => [
                'auto_action_success_rate' => ['status' => 'ok'],
                'policy_drift_rate' => ['status' => 'ok'],
            ],
        ]);
        $drift = \Mockery::mock(NajmHodaDecisionPolicyDriftService::class);
        $drift->shouldReceive('report')->twice()->andReturn([
            'status' => 'ok',
            'drift_rate' => 0.005,
        ]);

        $control = new NajmHodaAutonomyControlService($bus);
        $service = new NajmHodaSafeCodeOpsCanaryService($bus, $metrics, $drift, $control);

        $start = $service->startCanary(9, 'start_test', [10, 50, 100], 24);
        $this->assertTrue((bool) ($start['success'] ?? false));
        $this->assertSame('canary', (string) data_get($start, 'state.status', ''));
        $this->assertSame(10, (int) data_get($start, 'state.phase_percent', 0));

        $promote = $service->promote(9, 'promote_test');
        $this->assertTrue((bool) ($promote['success'] ?? false));
        $this->assertSame(50, (int) data_get($promote, 'state.phase_percent', 0));
    }

    public function test_auto_rollback_when_health_breaches_slo(): void
    {
        $bus = new InMemoryRuntimeEventBus(300);
        $metrics = \Mockery::mock(NajmHodaGovernanceMetricsAggregatorService::class);
        $metrics->shouldReceive('snapshot')->twice()->andReturn(
            [
                'event_count' => 12,
                'metrics' => ['auto_action_success_rate' => 0.95],
                'evaluation' => [
                    'auto_action_success_rate' => ['status' => 'ok'],
                ],
            ],
            [
                'event_count' => 15,
                'metrics' => ['auto_action_success_rate' => 0.70],
                'evaluation' => [
                    'auto_action_success_rate' => ['status' => 'breach'],
                ],
            ]
        );
        $drift = \Mockery::mock(NajmHodaDecisionPolicyDriftService::class);
        $drift->shouldReceive('report')->twice()->andReturn(
            ['status' => 'ok', 'drift_rate' => 0.005],
            ['status' => 'breach', 'drift_rate' => 0.08]
        );

        $control = new NajmHodaAutonomyControlService($bus);
        $service = new NajmHodaSafeCodeOpsCanaryService($bus, $metrics, $drift, $control);

        $start = $service->startCanary(10, 'start_test', [5, 25, 100], 24);
        $this->assertTrue((bool) ($start['success'] ?? false));

        $eval = $service->evaluate(true, 10, 'breach_auto_rollback');
        $this->assertTrue((bool) ($eval['success'] ?? false));
        $this->assertSame('rolled_back', (string) data_get($eval, 'state.status', ''));

        $override = $control->override();
        $this->assertSame('propose', (string) ($override['force_mode'] ?? ''));
        $this->assertFalse((bool) ($override['allow_apply_low_risk'] ?? true));
    }
}

