<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyAuditService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyGameDayService;
use App\Services\NajmHoda\Runtime\NajmHodaComplianceEvidenceService;
use App\Services\NajmHoda\Runtime\NajmHodaGovernanceAlertingService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ComplianceEvidenceServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.autonomy.compliance.window_hours' => 24,
            'najm-hoda.runtime.autonomy.human_escalation.notify_admins' => false,
        ]);

        Cache::flush();
    }

    public function test_build_pack_aggregates_core_evidence_sections(): void
    {
        $bus = new InMemoryRuntimeEventBus(500);
        $audit = new NajmHodaAutonomyAuditService($bus);
        $approval = new NajmHodaAutonomyApprovalService($bus, app(NotificationService::class));

        $alerting = $this->createMock(NajmHodaGovernanceAlertingService::class);
        $alerting->expects($this->once())
            ->method('history')
            ->with(200)
            ->willReturn([
                ['code' => 'GOV_APPROVAL_SLA_OVERDUE', 'severity' => 'critical'],
            ]);

        $gameday = $this->createMock(NajmHodaAutonomyGameDayService::class);
        $gameday->expects($this->once())
            ->method('history')
            ->with(50)
            ->willReturn([
                ['status' => 'pass', 'scenario_count' => 4],
            ]);

        $audit->record([
            'run_id' => 'run-test-1',
            'status' => 'completed',
            'executed' => true,
            'goals' => ['stabilize_operations'],
            'plan' => [['action' => 'run_ops_monitor']],
            'execution_results' => [['action' => 'run_ops_monitor', 'success' => true]],
        ]);

        $approval->requestApproval([
            'action' => 'run_ops_monitor',
            'risk' => 'high',
            'mode' => 'apply',
        ]);

        $service = new NajmHodaComplianceEvidenceService(
            $audit,
            $approval,
            $alerting,
            $gameday
        );

        $pack = $service->buildPack(24);

        $this->assertSame(24, (int) data_get($pack, 'meta.window_hours'));
        $this->assertNotEmpty((string) data_get($pack, 'meta.integrity_hash', ''));
        $this->assertGreaterThanOrEqual(1, (int) data_get($pack, 'summary.audit_traces', 0));
        $this->assertGreaterThanOrEqual(1, (int) data_get($pack, 'summary.approval_requests', 0));
        $this->assertGreaterThanOrEqual(1, (int) data_get($pack, 'summary.governance_alerts', 0));
        $this->assertGreaterThanOrEqual(1, (int) data_get($pack, 'summary.gameday_reports', 0));
    }
}
