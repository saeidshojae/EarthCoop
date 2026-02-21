<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\GroupActionExecutor;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GroupActionExecutorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.safety.rate_limit.max_actions_per_minute' => 60,
            'najm-hoda.runtime.safety.rate_limit.max_actions_per_action_per_minute' => 20,
            'najm-hoda.runtime.safety.circuit_breaker.failure_threshold' => 5,
            'najm-hoda.runtime.safety.circuit_breaker.failure_window_seconds' => 600,
            'najm-hoda.runtime.safety.circuit_breaker.cooldown_seconds' => 300,
        ]);
    }

    public function test_dry_run_returns_proposed_with_metadata(): void
    {
        $executor = app(GroupActionExecutor::class);

        $result = $executor->execute('group_action_execute', ['group_id' => 1], true, function () {
            return ['decision' => 'executed'];
        });

        $this->assertIsArray($result);
        $this->assertSame('proposed', $result['decision']);
        $this->assertSame('dry_run_enabled', $result['reason']);
        $this->assertTrue($result['context']['dry_run']);
        $this->assertSame('group_action_execute', $result['context']['action_type']);
    }

    public function test_executor_normalizes_minimal_success_response(): void
    {
        $executor = app(GroupActionExecutor::class);

        $result = $executor->execute('private_message', ['group_id' => 2], false, function () {
            return ['decision' => 'executed'];
        });

        $this->assertIsArray($result);
        $this->assertSame('executed', $result['decision']);
        $this->assertSame('executor_unknown', $result['reason']);
        $this->assertIsString($result['group_reply']);
        $this->assertFalse($result['context']['dry_run']);
        $this->assertSame('private_message', $result['context']['action_type']);
    }

    public function test_rate_limit_blocks_when_action_threshold_is_reached(): void
    {
        config([
            'najm-hoda.runtime.safety.rate_limit.max_actions_per_minute' => 10,
            'najm-hoda.runtime.safety.rate_limit.max_actions_per_action_per_minute' => 1,
            'najm-hoda.runtime.safety.circuit_breaker.failure_threshold' => 99,
        ]);

        $executor = app(GroupActionExecutor::class);

        $first = $executor->execute('group_action_execute', ['group_id' => 3], false, function () {
            return ['decision' => 'executed', 'reason' => 'ok', 'group_reply' => 'done'];
        });
        $second = $executor->execute('group_action_execute', ['group_id' => 3], false, function () {
            return ['decision' => 'executed', 'reason' => 'ok', 'group_reply' => 'done'];
        });

        $this->assertSame('executed', $first['decision']);
        $this->assertSame('skipped', $second['decision']);
        $this->assertSame('action_rate_limited', $second['reason']);
    }

    public function test_circuit_breaker_opens_after_repeated_failures(): void
    {
        config([
            'najm-hoda.runtime.safety.rate_limit.max_actions_per_minute' => 100,
            'najm-hoda.runtime.safety.rate_limit.max_actions_per_action_per_minute' => 100,
            'najm-hoda.runtime.safety.circuit_breaker.failure_threshold' => 2,
            'najm-hoda.runtime.safety.circuit_breaker.failure_window_seconds' => 600,
            'najm-hoda.runtime.safety.circuit_breaker.cooldown_seconds' => 300,
        ]);

        $executor = app(GroupActionExecutor::class);
        $calls = 0;

        $op = function () use (&$calls) {
            $calls++;
            return ['decision' => 'failed', 'reason' => 'forced_failure', 'group_reply' => 'failed'];
        };

        $first = $executor->execute('group_action_execute', ['group_id' => 4], false, $op);
        $second = $executor->execute('group_action_execute', ['group_id' => 4], false, $op);
        $third = $executor->execute('group_action_execute', ['group_id' => 4], false, $op);

        $this->assertSame('failed', $first['decision']);
        $this->assertSame('failed', $second['decision']);
        $this->assertSame('skipped', $third['decision']);
        $this->assertSame('circuit_breaker_open', $third['reason']);
        $this->assertSame(2, $calls);
    }
}

