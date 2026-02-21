<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyControlService;
use App\Services\NajmHoda\Runtime\NajmHodaOperatorActionExecutorV2;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OperatorActionExecutorV2Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.autonomy.executor.enabled' => true,
            'najm-hoda.runtime.autonomy.executor.max_retries' => 1,
            'najm-hoda.runtime.autonomy.executor.idempotency_ttl_minutes' => 60,
            'najm-hoda.runtime.autonomy.executor.default_action_cooldown_seconds' => 30,
            'najm-hoda.runtime.autonomy.executor.action_cooldowns.run_ops_monitor' => 30,
            'najm-hoda.runtime.autonomy.costs.daily_budget' => 1.0,
            'najm-hoda.runtime.autonomy.costs.monthly_budget' => 10.0,
            'najm-hoda.runtime.autonomy.costs.action_estimates.run_ops_monitor' => 0.01,
        ]);

        Cache::flush();
    }

    public function test_executor_runs_low_risk_apply_action_and_then_skips_duplicate(): void
    {
        $bus = new InMemoryRuntimeEventBus(200);
        $executor = new NajmHodaOperatorActionExecutorV2($bus);

        $plan = [[
            'action' => 'run_ops_monitor',
            'mode' => 'apply',
            'risk' => 'low',
            'input' => ['health_status' => 'warning'],
        ]];

        $first = $executor->execute($plan, 'run-1');
        $second = $executor->execute($plan, 'run-2');

        $this->assertSame('executed', (string) data_get($first, '0.status', ''));
        $this->assertContains((string) data_get($second, '0.reason', ''), ['idempotent_duplicate', 'cooldown_active']);

        $events = $bus->recent('najm_hoda.autonomy.executor.executed', 1);
        $this->assertNotEmpty($events);
    }

    public function test_executor_skips_propose_mode(): void
    {
        $bus = new InMemoryRuntimeEventBus(100);
        $executor = new NajmHodaOperatorActionExecutorV2($bus);

        $plan = [[
            'action' => 'propose_engagement_recommendations',
            'mode' => 'propose',
            'risk' => 'low',
            'input' => ['goal_count' => 2],
        ]];

        $result = $executor->execute($plan, 'run-3');
        $this->assertSame('skipped', (string) data_get($result, '0.status', ''));
        $this->assertSame('mode_not_apply', (string) data_get($result, '0.reason', ''));
    }

    public function test_executor_blocks_apply_action_when_budget_is_exceeded(): void
    {
        config([
            'najm-hoda.runtime.autonomy.costs.daily_budget' => 0.005,
            'najm-hoda.runtime.autonomy.costs.action_estimates.run_ops_monitor' => 0.01,
        ]);

        $bus = new InMemoryRuntimeEventBus(100);
        $executor = new NajmHodaOperatorActionExecutorV2($bus);

        $plan = [[
            'action' => 'run_ops_monitor',
            'mode' => 'apply',
            'risk' => 'low',
            'input' => ['health_status' => 'warning'],
        ]];

        $result = $executor->execute($plan, 'run-4');
        $this->assertSame('skipped', (string) data_get($result, '0.status', ''));
        $this->assertSame('daily_budget_exceeded', (string) data_get($result, '0.reason', ''));
    }

    public function test_executor_skips_actions_when_kill_switch_is_active(): void
    {
        config([
            'najm-hoda.runtime.autonomy.kill_switch.enabled' => true,
        ]);

        $bus = new InMemoryRuntimeEventBus(100);
        $controlService = new NajmHodaAutonomyControlService($bus);
        $controlService->activateKillSwitch(1, 'incident', 30);

        $executor = new NajmHodaOperatorActionExecutorV2($bus, null, $controlService);

        $plan = [[
            'action' => 'run_ops_monitor',
            'mode' => 'apply',
            'risk' => 'low',
            'input' => ['health_status' => 'warning'],
        ]];

        $result = $executor->execute($plan, 'run-5');
        $this->assertSame('skipped', (string) data_get($result, '0.status', ''));
        $this->assertSame('global_kill_switch_active', (string) data_get($result, '0.reason', ''));
    }
}
