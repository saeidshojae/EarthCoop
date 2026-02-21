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

        $accepted = $bus->recent('najm_hoda.autonomy.contract.accepted', 1);
        $this->assertNotEmpty($accepted);
        $this->assertSame('run_ops_monitor', data_get($accepted[0], 'payload.action'));
    }
}
