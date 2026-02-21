<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\NajmHodaGovernanceKpiCatalogService;
use Tests\TestCase;

class GovernanceKpiCatalogServiceTest extends TestCase
{
    public function test_governance_kpi_baseline_contains_required_metrics(): void
    {
        $service = new NajmHodaGovernanceKpiCatalogService();
        $baseline = $service->baseline();

        $this->assertIsArray($baseline);
        $this->assertArrayHasKey('auto_action_success_rate', $baseline);
        $this->assertArrayHasKey('autonomy_coverage_rate', $baseline);
        $this->assertArrayHasKey('mttr_reduction_rate', $baseline);
        $this->assertArrayHasKey('rollback_unwanted_rate', $baseline);
        $this->assertArrayHasKey('user_satisfaction_score', $baseline);
        $this->assertArrayHasKey('human_approval_latency_minutes', $baseline);
        $this->assertArrayHasKey('policy_drift_rate', $baseline);

        $this->assertSame('ratio', (string) data_get($baseline, 'auto_action_success_rate.unit'));
        $this->assertGreaterThan(0.0, (float) data_get($baseline, 'auto_action_success_rate.target_min', 0));
    }
}
