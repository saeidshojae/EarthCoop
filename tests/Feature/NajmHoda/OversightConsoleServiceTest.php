<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyAuditService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyControlService;
use App\Services\NajmHoda\Runtime\NajmHodaAdaptivePolicyLearningService;
use App\Services\NajmHoda\Runtime\NajmHodaDecisionPolicyDriftService;
use App\Services\NajmHoda\Runtime\NajmHodaDelegatedPermissionService;
use App\Services\NajmHoda\Runtime\NajmHodaGovernanceMetricsAggregatorService;
use App\Services\NajmHoda\Runtime\NajmHodaOversightConsoleService;
use App\Services\NajmHoda\Runtime\NajmHodaSafeCodeOpsCanaryService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OversightConsoleServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'najm-hoda.enabled' => true,
            'najm-hoda.runtime.autonomy.human_escalation.notify_admins' => false,
        ]);
        Cache::flush();
    }

    public function test_snapshot_aggregates_core_oversight_signals(): void
    {
        $bus = new InMemoryRuntimeEventBus(300);
        $approval = new NajmHodaAutonomyApprovalService($bus, \Mockery::mock(NotificationService::class));
        $control = new NajmHodaAutonomyControlService($bus);
        $audit = new NajmHodaAutonomyAuditService($bus);
        $delegation = new NajmHodaDelegatedPermissionService($bus);
        $metrics = \Mockery::mock(NajmHodaGovernanceMetricsAggregatorService::class);
        $drift = \Mockery::mock(NajmHodaDecisionPolicyDriftService::class);
        $policyLearning = new NajmHodaAdaptivePolicyLearningService($bus, $metrics, $drift);
        $codeOps = \Mockery::mock(NajmHodaSafeCodeOpsCanaryService::class);
        $codeOps->shouldReceive('status')->once()->andReturn([
            'status' => 'idle',
            'phase_percent' => null,
        ]);
        Cache::put('najm_hoda:autonomy:policy_learning:recommendations', [[
            'id' => 'plr-1',
            'status' => 'pending',
            'created_at' => now()->toIso8601String(),
            'recommended_override' => ['max_actions_per_run' => 2],
        ]], now()->addMinutes(60));
        Cache::put('najm_hoda:autonomy:policy_learning:analysis_history', [[
            'id' => 'ev-1',
            'type' => 'analysis',
            'recorded_at' => now()->toIso8601String(),
        ]], now()->addMinutes(60));

        Cache::put('najm_hoda:autonomy:approval:requests', [[
            'id' => 'req-1',
            'status' => 'pending',
            'action' => 'run_ops_monitor',
            'risk' => 'medium',
            'mode' => 'apply',
            'requested_at' => now()->subMinutes(20)->toIso8601String(),
            'deadline_at' => now()->subMinutes(5)->toIso8601String(),
            'decision_at' => null,
            'decision_by' => null,
            'decision_reason' => null,
            'context' => [],
            'plan_item' => ['action' => 'run_ops_monitor'],
        ]], now()->addMinutes(60));

        $delegation->grant([
            'principal_type' => 'user',
            'principal_id' => '15',
            'action' => 'run_ops_monitor',
            'scope' => 'autonomy:run_ops_monitor',
            'require_approval' => true,
        ]);

        $audit->record([
            'run_id' => 'run-1',
            'status' => 'failed',
            'executed' => false,
            'goals' => ['stabilize_operations'],
            'plan' => [['action' => 'run_ops_monitor']],
            'execution_results' => [['status' => 'failed']],
        ]);

        $bus->emit('najm_hoda.autonomy.delegation.denied', [
            'actor_id' => 99,
            'action' => 'run_ops_monitor',
            'reason' => 'no_active_delegation',
        ]);

        $service = new NajmHodaOversightConsoleService($bus, $approval, $control, $audit, $delegation, $policyLearning, $codeOps);
        $snapshot = $service->snapshot(50);

        $this->assertSame(1, (int) data_get($snapshot, 'approvals.pending_count', 0));
        $this->assertSame(1, (int) data_get($snapshot, 'approvals.overdue_count', 0));
        $this->assertSame(1, (int) data_get($snapshot, 'delegation.active_count', 0));
        $this->assertSame(1, (int) data_get($snapshot, 'delegation.require_approval_count', 0));
        $this->assertSame(1, (int) data_get($snapshot, 'adaptive_policy.pending_count', 0));
        $this->assertSame('idle', (string) data_get($snapshot, 'codeops_canary.status', ''));
        $this->assertSame(1, (int) data_get($snapshot, 'audit.failed_count', 0));
        $this->assertSame(1, (int) data_get($snapshot, 'events.risk_signals.delegation_denied', 0));
        $this->assertSame(1, (int) data_get($snapshot, 'delegation.event_summary.denied', 0));
        $this->assertSame('no_active_delegation', (string) data_get($snapshot, 'delegation.event_summary.recent_denied.0.reason', ''));

        $recommendationTypes = array_map(
            static fn (array $item): string => (string) ($item['type'] ?? ''),
            (array) ($snapshot['recommended_actions'] ?? [])
        );
        $this->assertContains('approval_backlog', $recommendationTypes);
    }
}
