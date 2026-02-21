<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyApprovalService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyAuditService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyControlService;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomousGoalLoopService;
use App\Services\NajmHoda\Runtime\NajmHodaCapabilityRegistry;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomySafetyGate;
use App\Services\NajmHoda\Runtime\NajmHodaObservabilityGraphService;
use App\Services\NajmHoda\Runtime\NajmHodaOperatorActionExecutorV2;
use App\Services\NajmHoda\Runtime\NajmHodaProactiveRecommendationService;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AutonomousGoalLoopTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'najm-hoda.enabled' => true,
            'najm-hoda.runtime.event_bus.driver' => 'in_memory',
            'najm-hoda.runtime.autonomy.enabled' => true,
            'najm-hoda.runtime.autonomy.context_limit' => 200,
            'najm-hoda.runtime.autonomy.plan_ttl_minutes' => 180,
            'najm-hoda.runtime.autonomy.max_goals_per_run' => 5,
            'najm-hoda.runtime.autonomy.allow_apply_low_risk' => false,
            'najm-hoda.runtime.autonomy.thresholds.warning_error_rate_percent' => 15,
            'najm-hoda.runtime.autonomy.thresholds.warning_unresolved_requests' => 4,
            'najm-hoda.runtime.autonomy.capabilities.run_ops_monitor.required_input' => ['health_status'],
            'najm-hoda.runtime.autonomy.capabilities.propose_engagement_recommendations.required_input' => ['goal_count'],
            'najm-hoda.runtime.autonomy.human_escalation.enabled' => true,
            'najm-hoda.runtime.autonomy.human_escalation.notify_admins' => false,
            'najm-hoda.runtime.autonomy.human_escalation.require_approval_for_apply_mode' => true,
            'najm-hoda.runtime.autonomy.human_escalation.fallback_to_propose' => true,
        ]);

        Cache::flush();
    }

    public function test_service_builds_goal_plan_and_emits_runtime_event(): void
    {
        $bus = new InMemoryRuntimeEventBus(200);
        $bus->emit('najm_hoda.request.received', ['request_id' => '1']);
        $bus->emit('najm_hoda.request.received', ['request_id' => '2']);
        $bus->emit('najm_hoda.response.failed', ['request_id' => '1']);

        $registry = new NajmHodaCapabilityRegistry($bus);
        $safetyGate = new NajmHodaAutonomySafetyGate($bus);
        $observability = new NajmHodaObservabilityGraphService($bus);
        $recommendations = new NajmHodaProactiveRecommendationService($bus);
        $executor = new NajmHodaOperatorActionExecutorV2($bus);
        $controlService = new NajmHodaAutonomyControlService($bus);
        $auditService = new NajmHodaAutonomyAuditService($bus);
        $approvalService = new NajmHodaAutonomyApprovalService($bus, app(NotificationService::class));
        $service = new NajmHodaAutonomousGoalLoopService($bus, $observability, $recommendations, $executor, $controlService, $auditService, $registry, $safetyGate, $approvalService);
        $result = $service->run(['stabilize_operations'], false, 100);

        $this->assertTrue((bool) ($result['executed'] ?? false));
        $this->assertSame('completed', (string) ($result['status'] ?? ''));
        $this->assertNotEmpty((array) ($result['plan'] ?? []));
        $this->assertSame('run_ops_monitor', data_get($result, 'plan.0.action'));
        $this->assertSame(1, (int) data_get($result, 'plan.0.contract_version'));
        $this->assertSame('propose', data_get($result, 'plan.0.mode'));
        $this->assertNotEmpty((array) ($result['recommendations'] ?? []));

        $cached = Cache::get('najm_hoda:autonomy:last_goal_plan');
        $this->assertIsArray($cached);
        $this->assertSame((string) ($result['run_id'] ?? ''), (string) ($cached['run_id'] ?? ''));

        $events = $bus->recent('najm_hoda.autonomy.goal_loop.executed', 1);
        $this->assertNotEmpty($events);
        $this->assertSame((string) ($result['run_id'] ?? ''), (string) data_get($events[0], 'payload.run_id'));
    }

    public function test_service_routes_apply_mode_to_human_approval_queue_and_fallbacks_to_propose(): void
    {
        config([
            'najm-hoda.runtime.autonomy.allow_apply_low_risk' => true,
            'najm-hoda.runtime.autonomy.human_escalation.enabled' => true,
            'najm-hoda.runtime.autonomy.human_escalation.require_approval_for_apply_mode' => true,
            'najm-hoda.runtime.autonomy.human_escalation.notify_admins' => false,
            'najm-hoda.runtime.autonomy.human_escalation.fallback_to_propose' => true,
        ]);

        $bus = new InMemoryRuntimeEventBus(200);
        $bus->emit('najm_hoda.request.received', ['request_id' => '1']);
        $bus->emit('najm_hoda.response.ready', ['request_id' => '1']);

        $registry = new NajmHodaCapabilityRegistry($bus);
        $safetyGate = new NajmHodaAutonomySafetyGate($bus);
        $observability = new NajmHodaObservabilityGraphService($bus);
        $recommendations = new NajmHodaProactiveRecommendationService($bus);
        $executor = new NajmHodaOperatorActionExecutorV2($bus);
        $controlService = new NajmHodaAutonomyControlService($bus);
        $auditService = new NajmHodaAutonomyAuditService($bus);
        $approvalService = new NajmHodaAutonomyApprovalService($bus, app(NotificationService::class));
        $service = new NajmHodaAutonomousGoalLoopService($bus, $observability, $recommendations, $executor, $controlService, $auditService, $registry, $safetyGate, $approvalService);

        $result = $service->run(['improve_user_experience'], true, 100);

        $this->assertTrue((bool) data_get($result, 'plan.0.requires_human_approval', false));
        $this->assertSame('pending', (string) data_get($result, 'plan.0.approval_status', ''));
        $this->assertSame('propose', (string) data_get($result, 'plan.0.mode', ''));
        $this->assertNotEmpty((string) data_get($result, 'plan.0.approval_request_id', ''));

        $approvalEvents = $bus->recent('najm_hoda.autonomy.approval.requested', 1);
        $this->assertNotEmpty($approvalEvents);
    }

    public function test_goal_loop_command_runs_successfully_and_persists_plan(): void
    {
        /** @var RuntimeEventBus $bus */
        $bus = app(RuntimeEventBus::class);
        $bus->clear();

        $bus->emit('najm_hoda.request.received', ['request_id' => '1']);
        $bus->emit('najm_hoda.response.ready', ['request_id' => '1']);

        $this->artisan('najm-hoda:goal-loop --goal=improve_user_experience --context-limit=50')
            ->assertExitCode(0);

        $cached = Cache::get('najm_hoda:autonomy:last_goal_plan');
        $this->assertIsArray($cached);
        $this->assertSame('completed', (string) ($cached['status'] ?? ''));

        $events = $bus->recent('najm_hoda.autonomy.goal_loop.executed', 1);
        $this->assertNotEmpty($events);
    }
}
