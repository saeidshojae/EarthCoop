<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyControlService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AutonomyControlServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::flush();
    }

    public function test_pause_and_resume_flow_updates_state(): void
    {
        $bus = new InMemoryRuntimeEventBus(100);
        $service = new NajmHodaAutonomyControlService($bus);

        $service->pause(10, 'maintenance', 30);
        $this->assertTrue($service->isPaused());
        $this->assertSame('maintenance', (string) ($service->state()['reason'] ?? ''));

        $service->resume(10, 'done');
        $this->assertFalse($service->isPaused());
    }

    public function test_override_can_force_mode_and_block_action(): void
    {
        $bus = new InMemoryRuntimeEventBus(100);
        $service = new NajmHodaAutonomyControlService($bus);

        $override = $service->setOverride('propose', ['run_ops_monitor'], false, 11, 'manual');

        $this->assertSame('propose', (string) ($override['force_mode'] ?? ''));
        $this->assertContains('run_ops_monitor', (array) ($override['blocked_actions'] ?? []));
        $this->assertFalse((bool) ($override['allow_apply_low_risk'] ?? true));
    }

    public function test_kill_switch_can_activate_and_deactivate(): void
    {
        config([
            'najm-hoda.runtime.autonomy.kill_switch.enabled' => true,
            'najm-hoda.runtime.autonomy.kill_switch.max_minutes' => 120,
        ]);

        $bus = new InMemoryRuntimeEventBus(100);
        $service = new NajmHodaAutonomyControlService($bus);

        $active = $service->activateKillSwitch(12, 'incident_response', 30);
        $this->assertTrue((bool) ($active['active'] ?? false));
        $this->assertTrue($service->isKillSwitchActive());

        $disabled = $service->deactivateKillSwitch(12, 'recovered');
        $this->assertFalse((bool) ($disabled['active'] ?? true));
        $this->assertFalse($service->isKillSwitchActive());
    }
}
