<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\FounderOps\FounderLowRiskDomainActionService;
use Tests\TestCase;

class FounderLowRiskDomainActionServiceTest extends TestCase
{
    public function test_only_explicit_canonical_handlers_are_supported(): void
    {
        $service = app(FounderLowRiskDomainActionService::class);

        $this->assertTrue($service->supports('runtime_health', 'run_read_only_diagnostic'));
        $this->assertTrue($service->supports('runtime_health', 'collect_health_snapshot'));
        $this->assertTrue($service->supports('support', 'assign_priority'));
        $this->assertFalse($service->supports('najm_bahar', 'execute_transaction'));
    }

    public function test_unknown_low_risk_action_fails_closed(): void
    {
        $result = app(FounderLowRiskDomainActionService::class)->execute('support', 'unknown_action');

        $this->assertFalse($result['success']);
        $this->assertSame('unsupported', $result['status']);
        $this->assertSame('no_canonical_low_risk_handler', $result['reason']);
    }
}
