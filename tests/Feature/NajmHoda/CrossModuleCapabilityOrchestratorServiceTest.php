<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyControlService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomySafetyGate;
use App\Services\NajmHoda\Runtime\NajmHodaCapabilityRegistry;
use App\Services\NajmHoda\Runtime\NajmHodaCompensatingTransactionService;
use App\Services\NajmHoda\Runtime\NajmHodaCrossModuleCapabilityOrchestratorService;
use App\Services\NajmHoda\Runtime\NajmHodaDelegatedPermissionService;
use App\Services\NajmHoda\Runtime\NajmHodaMultiHorizonGoalEngineService;
use App\Services\NajmHoda\Runtime\NajmHodaMultiHorizonGoalReviewService;
use App\Services\NajmHoda\Runtime\NajmHodaOperatorActionExecutorV2;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CrossModuleCapabilityOrchestratorServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'najm-hoda.enabled' => true,
            'najm-hoda.runtime.autonomy.orchestrator.enabled' => true,
            'najm-hoda.runtime.autonomy.allow_apply_low_risk' => true,
            'najm-hoda.runtime.autonomy.permissioning_v2.enabled' => true,
            'najm-hoda.runtime.autonomy.permissioning_v2.enforce_apply_requires_delegation' => true,
            'najm-hoda.runtime.autonomy.executor.enabled' => true,
            'najm-hoda.runtime.autonomy.executor.max_retries' => 1,
            'najm-hoda.runtime.autonomy.executor.idempotency_ttl_minutes' => 30,
            'najm-hoda.runtime.autonomy.executor.default_action_cooldown_seconds' => 0,
            'najm-hoda.runtime.autonomy.executor.action_cooldowns.run_ops_monitor' => 0,
            'najm-hoda.runtime.autonomy.executor.action_cooldowns.propose_engagement_recommendations' => 0,
            'najm-hoda.runtime.autonomy.orchestrator.compensation.fallback_to_capability_rollback' => true,
            'najm-hoda.runtime.autonomy.safety.enabled' => true,
            'najm-hoda.runtime.autonomy.safety.max_actions_per_run' => 3,
            'najm-hoda.runtime.autonomy.safety.allowed_risk_levels' => ['low'],
            'najm-hoda.runtime.autonomy.safety.allowed_actions' => [
                'run_ops_monitor',
                'propose_engagement_recommendations',
                'rollback_ops_monitor',
                'rollback_engagement_recommendations',
            ],
            'najm-hoda.runtime.autonomy.safety.action_goal_scope.run_ops_monitor' => ['stabilize_operations'],
            'najm-hoda.runtime.autonomy.safety.action_goal_scope.propose_engagement_recommendations' => ['stabilize_operations'],
            'najm-hoda.runtime.autonomy.safety.action_goal_scope.rollback_ops_monitor' => ['stabilize_operations'],
            'najm-hoda.runtime.autonomy.safety.action_goal_scope.rollback_engagement_recommendations' => ['stabilize_operations'],
            'najm-hoda.runtime.autonomy.capabilities.run_ops_monitor.required_input' => ['health_status'],
            'najm-hoda.runtime.autonomy.capabilities.propose_engagement_recommendations.required_input' => ['goal_count'],
            'najm-hoda.runtime.autonomy.capabilities.rollback_ops_monitor.required_input' => ['origin_action', 'origin_run_id'],
            'najm-hoda.runtime.autonomy.capabilities.rollback_engagement_recommendations.required_input' => ['origin_action', 'origin_run_id'],
            'najm-hoda.runtime.autonomy.costs.daily_budget' => 5,
            'najm-hoda.runtime.autonomy.costs.monthly_budget' => 100,
        ]);

        Cache::flush();
    }

    public function test_orchestrator_executes_chain_successfully(): void
    {
        $service = $this->makeService();

        $result = $service->orchestrate([
            [
                'action' => 'run_ops_monitor',
                'priority' => 'stability',
                'reason' => 'chain_test',
                'input' => ['health_status' => 'warning'],
                'preconditions' => ['kill_switch_off'],
            ],
            [
                'action' => 'propose_engagement_recommendations',
                'priority' => 'growth',
                'reason' => 'chain_test',
                'input' => ['goal_count' => 1, 'health_status' => 'warning'],
                'preconditions' => ['kill_switch_off', 'no_previous_failures'],
            ],
        ], ['stabilize_operations'], true);

        $this->assertTrue((bool) ($result['executed'] ?? false));
        $this->assertSame('completed', (string) ($result['status'] ?? ''));
        $this->assertCount(2, (array) ($result['steps'] ?? []));
        $this->assertSame('executed', (string) data_get($result, 'steps.0.status', ''));
        $this->assertSame('executed', (string) data_get($result, 'steps.1.status', ''));
    }

    public function test_orchestrator_rolls_back_when_second_step_fails_contract(): void
    {
        $service = $this->makeService();

        $result = $service->orchestrate([
            [
                'action' => 'run_ops_monitor',
                'priority' => 'stability',
                'reason' => 'chain_test',
                'input' => ['health_status' => 'warning'],
                'preconditions' => ['kill_switch_off'],
            ],
            [
                'action' => 'unsupported_action',
                'priority' => 'growth',
                'reason' => 'chain_test',
                'input' => ['goal_count' => 1],
                'preconditions' => ['kill_switch_off', 'no_previous_failures'],
            ],
        ], ['stabilize_operations'], true);

        $this->assertFalse((bool) ($result['executed'] ?? true));
        $this->assertSame('failed', (string) ($result['status'] ?? ''));
        $this->assertSame('contract_rejected', (string) ($result['reason'] ?? ''));
        $this->assertGreaterThan(0, count((array) ($result['rollback'] ?? [])));
        $this->assertSame('run_ops_monitor', (string) data_get($result, 'rollback.0.action', ''));
        $this->assertSame('rollback_ops_monitor', (string) data_get($result, 'rollback.0.rollback_action', ''));
        $this->assertSame('executed', (string) data_get($result, 'rollback.0.status', ''));
    }

    public function test_orchestrator_treats_propose_mode_as_planned_not_failed(): void
    {
        $service = $this->makeService();

        $result = $service->orchestrate([
            [
                'action' => 'run_ops_monitor',
                'priority' => 'stability',
                'reason' => 'chain_test',
                'input' => ['health_status' => 'warning'],
                'preconditions' => ['kill_switch_off'],
            ],
        ], ['stabilize_operations'], false);

        $this->assertTrue((bool) ($result['executed'] ?? false));
        $this->assertSame('completed', (string) ($result['status'] ?? ''));
        $this->assertSame('planned', (string) data_get($result, 'steps.0.status', ''));
        $this->assertSame('propose_mode', (string) data_get($result, 'steps.0.reason', ''));
    }

    public function test_orchestrator_passes_group_and_role_context_to_delegation_check(): void
    {
        config(['najm-hoda.runtime.autonomy.permissioning_v2.enforce_apply_requires_delegation' => true]);

        $delegation = \Mockery::mock(NajmHodaDelegatedPermissionService::class);
        $delegation->shouldReceive('authorize')->once()->withArgs(
            static function ($actorId, $action, $scope, $context): bool {
                return $actorId === 15
                    && $action === 'run_ops_monitor'
                    && $scope === 'autonomy:run_ops_monitor'
                    && is_array($context)
                    && (($context['group_id'] ?? null) === 88)
                    && (($context['role_slugs'][0] ?? null) === 'ops-admin');
            }
        )->andReturn(['allowed' => true, 'reason' => 'delegation_match']);

        $service = $this->makeService($delegation);
        $result = $service->orchestrate([
            [
                'action' => 'run_ops_monitor',
                'priority' => 'stability',
                'reason' => 'delegation_context_test',
                'input' => [
                    'health_status' => 'warning',
                    'scope' => 'autonomy:run_ops_monitor',
                    'group_id' => 88,
                    'role_slugs' => ['ops-admin'],
                ],
                'preconditions' => ['kill_switch_off'],
            ],
        ], ['stabilize_operations'], true, 15);

        $this->assertTrue((bool) ($result['executed'] ?? false));
        $this->assertSame('completed', (string) ($result['status'] ?? ''));
    }

    protected function makeService(?NajmHodaDelegatedPermissionService $delegation = null): NajmHodaCrossModuleCapabilityOrchestratorService
    {
        $bus = new InMemoryRuntimeEventBus(300);
        $registry = new NajmHodaCapabilityRegistry($bus);
        $safety = new NajmHodaAutonomySafetyGate($bus);
        $executor = new NajmHodaOperatorActionExecutorV2($bus);
        $control = new NajmHodaAutonomyControlService($bus);
        $compensation = \Mockery::mock(NajmHodaCompensatingTransactionService::class);
        $compensation->shouldReceive('execute')->andReturn([
            'handled' => false,
            'status' => 'skipped',
            'reason' => 'no_compensation_spec',
        ]);
        if ($delegation === null) {
            $delegation = \Mockery::mock(NajmHodaDelegatedPermissionService::class);
            $delegation->shouldReceive('authorize')->andReturn(['allowed' => true]);
        }
        $approval = new NajmHodaAutonomyApprovalService($bus, app(NotificationService::class));
        $engine = \Mockery::mock(NajmHodaMultiHorizonGoalEngineService::class);
        $review = \Mockery::mock(NajmHodaMultiHorizonGoalReviewService::class);

        return new NajmHodaCrossModuleCapabilityOrchestratorService(
            $bus,
            $registry,
            $safety,
            $executor,
            $compensation,
            $control,
            $delegation,
            $approval,
            $engine,
            $review
        );
    }
}
