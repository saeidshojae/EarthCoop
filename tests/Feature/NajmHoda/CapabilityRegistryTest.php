<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaCapabilityRegistry;
use Tests\TestCase;

class CapabilityRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.autonomy.allow_apply_low_risk' => true,
            'najm-hoda.runtime.autonomy.permissioning_v2.enabled' => true,
            'najm-hoda.runtime.autonomy.permissioning_v2.enforce_apply_requires_delegation' => true,
            'najm-hoda.runtime.autonomy.capabilities.run_ops_monitor' => [
                'enabled' => true,
                'version' => 2,
                'risk' => 'low',
                'mode' => 'propose',
                'required_input' => ['health_status'],
                'optional_input' => ['error_rate_percent'],
                'output' => ['plan_ref'],
            ],
        ]);
    }

    public function test_registry_rejects_unknown_action_contract(): void
    {
        $bus = new InMemoryRuntimeEventBus(100);
        $registry = new NajmHodaCapabilityRegistry($bus);

        $planned = $registry->makePlannedAction(
            'unknown_action',
            ['health_status' => 'warning'],
            'stability',
            'test',
            false,
            ['stabilize_operations']
        );

        $this->assertNull($planned);
        $rejected = $bus->recent('najm_hoda.autonomy.contract.rejected', 1);
        $this->assertNotEmpty($rejected);
        $this->assertSame('unknown_action_contract', data_get($rejected[0], 'payload.reason'));
    }

    public function test_registry_accepts_valid_contract_and_traces_accept_event(): void
    {
        $bus = new InMemoryRuntimeEventBus(100);
        $registry = new NajmHodaCapabilityRegistry($bus);

        $planned = $registry->makePlannedAction(
            'run_ops_monitor',
            ['health_status' => 'warning'],
            'stability',
            'health_signals_above_warning',
            false,
            ['stabilize_operations']
        );

        $this->assertIsArray($planned);
        $this->assertSame('run_ops_monitor', (string) ($planned['action'] ?? ''));
        $this->assertSame(2, (int) ($planned['contract_version'] ?? 0));
        $this->assertSame('propose', (string) ($planned['mode'] ?? ''));

        $accepted = $bus->recent('najm_hoda.autonomy.contract.accepted', 1);
        $this->assertNotEmpty($accepted);
        $this->assertSame('run_ops_monitor', data_get($accepted[0], 'payload.action'));
    }

    public function test_apply_request_can_be_planned_only_with_delegation_enforcement_enabled(): void
    {
        $registry = new NajmHodaCapabilityRegistry(new InMemoryRuntimeEventBus(100));

        $planned = $registry->makePlannedAction(
            'run_ops_monitor',
            ['health_status' => 'warning'],
            'stability',
            'test_apply',
            true,
            ['stabilize_operations']
        );

        $this->assertSame('apply', (string) ($planned['mode'] ?? ''));
    }

    public function test_apply_request_falls_back_to_propose_when_delegation_enforcement_is_disabled(): void
    {
        config(['najm-hoda.runtime.autonomy.permissioning_v2.enforce_apply_requires_delegation' => false]);
        $registry = new NajmHodaCapabilityRegistry(new InMemoryRuntimeEventBus(100));

        $planned = $registry->makePlannedAction(
            'run_ops_monitor',
            ['health_status' => 'warning'],
            'stability',
            'test_apply',
            true,
            ['stabilize_operations']
        );

        $this->assertSame('propose', (string) ($planned['mode'] ?? ''));
    }

    public function test_apply_request_falls_back_to_propose_when_permissioning_is_disabled(): void
    {
        config(['najm-hoda.runtime.autonomy.permissioning_v2.enabled' => false]);
        $registry = new NajmHodaCapabilityRegistry(new InMemoryRuntimeEventBus(100));

        $planned = $registry->makePlannedAction(
            'run_ops_monitor',
            ['health_status' => 'warning'],
            'stability',
            'test_apply',
            true,
            ['stabilize_operations']
        );

        $this->assertSame('propose', (string) ($planned['mode'] ?? ''));
    }
}
