<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaAdaptivePolicyLearningService;
use App\Services\NajmHoda\Runtime\NajmHodaDecisionPolicyDriftService;
use App\Services\NajmHoda\Runtime\NajmHodaGovernanceMetricsAggregatorService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdaptivePolicyLearningServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::flush();
    }

    public function test_analyze_applies_override_when_drift_is_in_breach(): void
    {
        $bus = new InMemoryRuntimeEventBus(200);
        $metrics = \Mockery::mock(NajmHodaGovernanceMetricsAggregatorService::class);
        $metrics->shouldReceive('snapshot')->once()->andReturn([
            'metrics' => [
                'auto_action_success_rate' => 0.82,
            ],
        ]);

        $drift = \Mockery::mock(NajmHodaDecisionPolicyDriftService::class);
        $drift->shouldReceive('report')->once()->andReturn([
            'drift_rate' => 0.08,
            'status' => 'breach',
            'total_decisions' => 40,
        ]);

        $service = new NajmHodaAdaptivePolicyLearningService($bus, $metrics, $drift);
        $report = $service->analyze(24, true);

        $this->assertTrue((bool) ($report['should_apply'] ?? false));
        $this->assertTrue((bool) ($report['applied'] ?? false));
        $this->assertNotNull($report['recommendation_id'] ?? null);
        $this->assertSame(1, (int) data_get($report, 'recommended_override.max_actions_per_run', 0));
        $this->assertFalse((bool) data_get($report, 'recommended_override.allow_apply_low_risk', true));

        $override = Cache::get('najm_hoda:autonomy:adaptive_safety_override');
        $this->assertIsArray($override);
        $this->assertSame(1, (int) ($override['max_actions_per_run'] ?? 0));
        $this->assertSame('auto_applied', (string) data_get($report, 'review.status', ''));
    }

    public function test_recommendation_review_supports_operator_approve_and_evidence(): void
    {
        $bus = new InMemoryRuntimeEventBus(200);
        $metrics = \Mockery::mock(NajmHodaGovernanceMetricsAggregatorService::class);
        $metrics->shouldReceive('snapshot')->once()->andReturn([
            'metrics' => [
                'auto_action_success_rate' => 0.85,
            ],
        ]);

        $drift = \Mockery::mock(NajmHodaDecisionPolicyDriftService::class);
        $drift->shouldReceive('report')->once()->andReturn([
            'drift_rate' => 0.06,
            'status' => 'warning',
            'total_decisions' => 22,
        ]);

        $service = new NajmHodaAdaptivePolicyLearningService($bus, $metrics, $drift);
        $report = $service->analyze(24, false);

        $this->assertTrue((bool) ($report['should_apply'] ?? false));
        $this->assertFalse((bool) ($report['applied'] ?? false));
        $this->assertSame('pending', (string) data_get($report, 'review.status', ''));

        $recommendationId = (string) ($report['recommendation_id'] ?? '');
        $this->assertNotSame('', $recommendationId);

        $review = $service->reviewRecommendation($recommendationId, 'approve', 77, 'operator_confirmed');
        $this->assertTrue((bool) ($review['success'] ?? false));
        $this->assertTrue((bool) ($review['applied'] ?? false));
        $this->assertSame('approved', (string) data_get($review, 'recommendation.status', ''));

        $evidence = $service->recentEvidence(20);
        $types = array_map(static fn (array $row): string => (string) ($row['type'] ?? ''), $evidence);
        $this->assertContains('analysis', $types);
        $this->assertContains('recommendation_reviewed', $types);
    }
}
