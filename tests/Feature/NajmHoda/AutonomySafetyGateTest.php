<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomySafetyGate;
use Tests\TestCase;

class AutonomySafetyGateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.autonomy.safety.enabled' => true,
            'najm-hoda.runtime.autonomy.safety.max_actions_per_run' => 1,
            'najm-hoda.runtime.autonomy.safety.allowed_risk_levels' => ['low'],
            'najm-hoda.runtime.autonomy.safety.allowed_actions' => ['run_ops_monitor'],
            'najm-hoda.runtime.autonomy.safety.blocked_actions' => [],
            'najm-hoda.runtime.autonomy.safety.action_goal_scope.run_ops_monitor' => ['stabilize_operations'],
        ]);
    }

    public function test_safety_gate_blocks_scope_mismatch(): void
    {
        $bus = new InMemoryRuntimeEventBus(100);
        $gate = new NajmHodaAutonomySafetyGate($bus);

        $result = $gate->evaluate(
            ['action' => 'run_ops_monitor', 'risk' => 'low'],
            ['improve_user_experience'],
            0
        );

        $this->assertFalse((bool) ($result['allowed'] ?? true));
        $this->assertSame('scope_goal_mismatch', (string) ($result['reason'] ?? ''));

        $blocked = $bus->recent('najm_hoda.autonomy.safety.blocked', 1);
        $this->assertNotEmpty($blocked);
        $this->assertSame('scope_goal_mismatch', data_get($blocked[0], 'payload.reason'));
    }

    public function test_safety_gate_blocks_budget_exceeded(): void
    {
        $bus = new InMemoryRuntimeEventBus(100);
        $gate = new NajmHodaAutonomySafetyGate($bus);

        $result = $gate->evaluate(
            ['action' => 'run_ops_monitor', 'risk' => 'low'],
            ['stabilize_operations'],
            1
        );

        $this->assertFalse((bool) ($result['allowed'] ?? true));
        $this->assertSame('budget_exceeded', (string) ($result['reason'] ?? ''));
    }
}
