<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\NajmHodaOrchestrator;
use App\Services\NajmHoda\Runtime\NajmHodaEntryPolicy;
use App\Services\NajmHoda\Runtime\NajmHodaExecutionService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EntryPolicyAndExecutionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'cache.default' => 'array',
            'najm-hoda.enabled' => true,
            'najm-hoda.runtime.entry_policy.rate_limit.max_requests_per_minute' => 5,
            'najm-hoda.runtime.entry_policy.rate_limit.max_chat_requests_per_minute' => 2,
        ]);
    }

    public function test_entry_policy_denies_when_system_is_disabled(): void
    {
        config(['najm-hoda.enabled' => false]);
        $policy = app(NajmHodaEntryPolicy::class);

        $result = $policy->check('api.chat', 10, '127.0.0.1', true);

        $this->assertFalse($result['allowed']);
        $this->assertSame('NAJM_HODA_DISABLED', $result['code']);
        $this->assertSame(503, $result['status']);
    }

    public function test_entry_policy_rate_limits_chat_scope(): void
    {
        $policy = app(NajmHodaEntryPolicy::class);

        $first = $policy->check('api.chat', 20, '127.0.0.1', true);
        $second = $policy->check('api.chat', 20, '127.0.0.1', true);
        $third = $policy->check('api.chat', 20, '127.0.0.1', true);

        $this->assertTrue($first['allowed']);
        $this->assertTrue($second['allowed']);
        $this->assertFalse($third['allowed']);
        $this->assertSame('NAJM_HODA_RATE_LIMITED', $third['code']);
        $this->assertSame(429, $third['status']);
    }

    public function test_entry_policy_applies_ops_rate_multiplier(): void
    {
        Cache::put('najm_hoda:ops:entry_rate_multiplier', 0.5, now()->addMinutes(5));
        $policy = app(NajmHodaEntryPolicy::class);

        $first = $policy->check('api.chat', 21, '127.0.0.1', true);
        $second = $policy->check('api.chat', 21, '127.0.0.1', true);

        $this->assertTrue($first['allowed']);
        $this->assertFalse($second['allowed']);
        $this->assertSame('NAJM_HODA_RATE_LIMITED', $second['code']);
    }

    public function test_execution_service_normalizes_success_response(): void
    {
        $orchestrator = $this->createMock(NajmHodaOrchestrator::class);
        $orchestrator->method('route')->willReturn([
            'success' => true,
            'message' => 'ok',
            'agent' => 'steward',
            'agent_persian_name' => 'نجم‌هدا',
            'agent_icon' => '🤖',
            'suggestions' => ['s1'],
        ]);

        $service = app(NajmHodaExecutionService::class);
        $result = $service->executeChat($orchestrator, 'hello', []);

        $this->assertTrue($result['success']);
        $this->assertSame('ok', $result['message']);
        $this->assertSame('steward', $result['agent']);
        $this->assertArrayHasKey('request_id', $result);
        $this->assertArrayHasKey('response_time_ms', $result);
    }

    public function test_execution_service_normalizes_failure_response(): void
    {
        $orchestrator = $this->createMock(NajmHodaOrchestrator::class);
        $orchestrator->method('route')->willReturn([
            'success' => false,
            'message' => 'failed',
            'agent' => 'system',
            'error' => 'E_FAIL',
        ]);

        $service = app(NajmHodaExecutionService::class);
        $result = $service->executeChat($orchestrator, 'hello', []);

        $this->assertFalse($result['success']);
        $this->assertSame('failed', $result['message']);
        $this->assertSame('system', $result['agent']);
        $this->assertSame('E_FAIL', $result['error']);
        $this->assertArrayHasKey('request_id', $result);
        $this->assertArrayHasKey('response_time_ms', $result);
    }

    public function test_execution_service_handles_orchestrator_exception(): void
    {
        $orchestrator = $this->createMock(NajmHodaOrchestrator::class);
        $orchestrator
            ->method('route')
            ->willThrowException(new \RuntimeException('boom'));

        $service = app(NajmHodaExecutionService::class);
        $result = $service->executeChat($orchestrator, 'hello', []);

        $this->assertFalse($result['success']);
        $this->assertSame('system', $result['agent']);
        $this->assertArrayHasKey('request_id', $result);
        $this->assertArrayHasKey('response_time_ms', $result);
    }
}
