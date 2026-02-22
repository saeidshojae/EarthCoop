<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyAuditService;
use App\Services\NajmHoda\Runtime\NajmHodaContinuousEvaluationHarnessService;
use App\Services\NajmHoda\Runtime\NajmHodaDecisionPolicyDriftService;
use App\Services\NajmHoda\Runtime\NajmHodaGovernanceMetricsAggregatorService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ContinuousEvaluationHarnessServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.autonomy.evaluation.notify_admins' => false,
        ]);
        Cache::flush();
    }

    public function test_run_produces_breach_when_quality_and_drift_are_bad(): void
    {
        $bus = new InMemoryRuntimeEventBus(300);
        $metrics = \Mockery::mock(NajmHodaGovernanceMetricsAggregatorService::class);
        $metrics->shouldReceive('snapshot')->once()->andReturn([
            'event_count' => 30,
            'metrics' => [
                'auto_action_success_rate' => 0.60,
                'user_satisfaction_score' => 0.45,
                'human_approval_latency_minutes' => 80,
            ],
            'evaluation' => [
                'auto_action_success_rate' => ['status' => 'breach'],
            ],
        ]);
        $drift = \Mockery::mock(NajmHodaDecisionPolicyDriftService::class);
        $drift->shouldReceive('report')->once()->andReturn([
            'status' => 'breach',
            'drift_rate' => 0.1,
            'drift_count' => 12,
            'top_reasons' => [['reason' => 'high_risk_blocked', 'count' => 6]],
        ]);
        $audit = \Mockery::mock(NajmHodaAutonomyAuditService::class);
        $audit->shouldReceive('history')->once()->andReturn([
            ['status' => 'failed'],
            ['status' => 'failed'],
            ['status' => 'ok'],
        ]);

        $service = new NajmHodaContinuousEvaluationHarnessService(
            $bus,
            $metrics,
            $drift,
            $audit,
            \Mockery::mock(NotificationService::class)
        );

        $report = $service->run(24, false);
        $this->assertSame('breach', (string) ($report['status'] ?? 'ok'));
        $this->assertGreaterThan(0, (int) ($report['alert_count'] ?? 0));
        $this->assertSame('breach', (string) data_get($report, 'decision_quality.status', 'ok'));
    }

    public function test_run_detects_safety_regression_against_previous_report(): void
    {
        $bus = new InMemoryRuntimeEventBus(300);
        $metrics = \Mockery::mock(NajmHodaGovernanceMetricsAggregatorService::class);
        $metrics->shouldReceive('snapshot')->twice()->andReturn([
            'event_count' => 20,
            'metrics' => [
                'auto_action_success_rate' => 0.95,
                'user_satisfaction_score' => 0.9,
                'human_approval_latency_minutes' => 20,
            ],
            'evaluation' => [
                'auto_action_success_rate' => ['status' => 'ok'],
            ],
        ]);
        $drift = \Mockery::mock(NajmHodaDecisionPolicyDriftService::class);
        $drift->shouldReceive('report')->twice()->andReturn([
            'status' => 'ok',
            'drift_rate' => 0.01,
            'drift_count' => 1,
            'top_reasons' => [],
        ]);
        $audit = \Mockery::mock(NajmHodaAutonomyAuditService::class);
        $audit->shouldReceive('history')->twice()->andReturn(
            [['status' => 'ok'], ['status' => 'ok'], ['status' => 'ok'], ['status' => 'ok']],
            [['status' => 'failed'], ['status' => 'failed'], ['status' => 'ok'], ['status' => 'ok']]
        );

        $service = new NajmHodaContinuousEvaluationHarnessService(
            $bus,
            $metrics,
            $drift,
            $audit,
            \Mockery::mock(NotificationService::class)
        );

        $first = $service->run(24, false);
        $second = $service->run(24, false);

        $this->assertSame('ok', (string) data_get($first, 'safety_regression.status', 'warning'));
        $this->assertContains(
            (string) data_get($second, 'safety_regression.status', 'ok'),
            ['warning', 'breach']
        );
        $this->assertGreaterThan(0.0, (float) data_get($second, 'safety_regression.failure_rate_delta', 0));
    }
}

