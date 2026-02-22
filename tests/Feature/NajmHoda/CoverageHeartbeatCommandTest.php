<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CoverageHeartbeatCommandTest extends TestCase
{
    public function test_command_emits_non_probe_heartbeat_events_for_all_families(): void
    {
        config([
            'najm-hoda.enabled' => true,
            'najm-hoda.runtime.coverage_kpi.heartbeat.enabled' => true,
        ]);

        $bus = new InMemoryRuntimeEventBus(200);
        $this->app->instance(RuntimeEventBus::class, $bus);

        Artisan::call('najm-hoda:coverage-heartbeat');

        $this->assertNotEmpty($bus->recent('najm_hoda.input.support.service.health_snapshot.succeeded', 1));
        $this->assertNotEmpty($bus->recent('najm_hoda.input.auth.service.health_snapshot.succeeded', 1));
        $this->assertNotEmpty($bus->recent('najm_hoda.input.content.service.health_snapshot.succeeded', 1));
        $this->assertNotEmpty($bus->recent('najm_hoda.input.najm_bahar.service.health_snapshot.succeeded', 1));
        $this->assertNotEmpty($bus->recent('najm_hoda.input.group_health_snapshot.succeeded', 1));
    }
}

