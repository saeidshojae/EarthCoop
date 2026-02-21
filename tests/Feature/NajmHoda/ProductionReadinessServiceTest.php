<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyGameDayService;
use App\Services\NajmHoda\Runtime\NajmHodaComplianceEvidenceService;
use App\Services\NajmHoda\Runtime\NajmHodaDecisionPolicyDriftService;
use App\Services\NajmHoda\Runtime\NajmHodaGovernanceMetricsAggregatorService;
use App\Services\NajmHoda\Runtime\NajmHodaProductionReadinessService;
use App\Services\NajmHoda\Runtime\NajmHodaRunbookRegistryService;
use Tests\TestCase;

class ProductionReadinessServiceTest extends TestCase
{
    public function test_review_returns_no_go_when_blockers_exist(): void
    {
        $bus = new InMemoryRuntimeEventBus(100);

        $governance = $this->createMock(NajmHodaGovernanceMetricsAggregatorService::class);
        $governance->method('snapshot')->willReturn([
            'evaluation' => [
                'auto_action_success_rate' => ['status' => 'breach'],
            ],
        ]);

        $drift = $this->createMock(NajmHodaDecisionPolicyDriftService::class);
        $drift->method('report')->willReturn([
            'status' => 'breach',
            'drift_rate' => 0.2,
            'drift_count' => 10,
        ]);

        $runbooks = $this->createMock(NajmHodaRunbookRegistryService::class);
        $runbooks->method('readiness')->willReturn([
            'status' => 'breach',
            'readiness_ratio' => 0.5,
        ]);
        $runbooks->method('all')->willReturn([]);

        $approvals = $this->createMock(NajmHodaAutonomyApprovalService::class);
        $approvals->method('pending')->willReturn([
            ['sla_status' => 'overdue'],
        ]);

        $gameday = $this->createMock(NajmHodaAutonomyGameDayService::class);
        $gameday->method('history')->willReturn([
            ['status' => 'fail'],
        ]);

        $compliance = $this->createMock(NajmHodaComplianceEvidenceService::class);
        $compliance->method('buildPack')->willReturn([
            'meta' => ['integrity_hash' => 'ok'],
            'summary' => ['audit_traces' => 0, 'runtime_events' => 0],
        ]);

        $service = new NajmHodaProductionReadinessService(
            $bus,
            $governance,
            $drift,
            $runbooks,
            $approvals,
            $gameday,
            $compliance
        );

        $review = $service->review(24);

        $this->assertSame('no_go', (string) ($review['decision'] ?? ''));
        $this->assertGreaterThan(0, (int) ($review['blocker_count'] ?? 0));
    }

    public function test_review_returns_go_when_all_checks_pass(): void
    {
        $bus = new InMemoryRuntimeEventBus(100);

        $governance = $this->createMock(NajmHodaGovernanceMetricsAggregatorService::class);
        $governance->method('snapshot')->willReturn([
            'evaluation' => [
                'auto_action_success_rate' => ['status' => 'ok'],
                'policy_drift_rate' => ['status' => 'ok'],
            ],
        ]);

        $drift = $this->createMock(NajmHodaDecisionPolicyDriftService::class);
        $drift->method('report')->willReturn([
            'status' => 'ok',
            'drift_rate' => 0.0,
            'drift_count' => 0,
        ]);

        $runbooks = $this->createMock(NajmHodaRunbookRegistryService::class);
        $runbooks->method('readiness')->willReturn([
            'status' => 'ready',
            'readiness_ratio' => 1.0,
        ]);
        $runbooks->method('all')->willReturn([
            ['id' => 'incident_response', 'status' => 'active'],
            ['id' => 'degraded_mode', 'status' => 'active'],
            ['id' => 'override_control', 'status' => 'active'],
            ['id' => 'recovery_validation', 'status' => 'active'],
        ]);

        $approvals = $this->createMock(NajmHodaAutonomyApprovalService::class);
        $approvals->method('pending')->willReturn([]);

        $gameday = $this->createMock(NajmHodaAutonomyGameDayService::class);
        $gameday->method('history')->willReturn([
            ['status' => 'pass'],
            ['status' => 'pass'],
        ]);

        $compliance = $this->createMock(NajmHodaComplianceEvidenceService::class);
        $compliance->method('buildPack')->willReturn([
            'meta' => ['integrity_hash' => 'ok'],
            'summary' => ['audit_traces' => 5, 'runtime_events' => 20],
        ]);

        $service = new NajmHodaProductionReadinessService(
            $bus,
            $governance,
            $drift,
            $runbooks,
            $approvals,
            $gameday,
            $compliance
        );

        $review = $service->review(24);

        $this->assertSame('go', (string) ($review['decision'] ?? ''));
        $this->assertSame(0, (int) ($review['blocker_count'] ?? -1));
    }
}
