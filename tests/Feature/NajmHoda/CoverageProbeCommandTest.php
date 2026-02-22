<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CoverageProbeCommandTest extends TestCase
{
    public function test_command_emits_probe_events_for_all_critical_families(): void
    {
        config([
            'najm-hoda.enabled' => true,
            'najm-hoda.runtime.coverage_kpi.probe.enabled' => true,
        ]);

        $bus = new InMemoryRuntimeEventBus(200);
        $this->app->instance(RuntimeEventBus::class, $bus);

        Artisan::call('najm-hoda:coverage-probe');

        $this->assertNotEmpty($bus->recent('najm_hoda.input.support.service.coverage_probe.succeeded', 1));
        $this->assertNotEmpty($bus->recent('najm_hoda.input.auth.service.coverage_probe.succeeded', 1));
        $this->assertNotEmpty($bus->recent('najm_hoda.input.content.service.coverage_probe.succeeded', 1));
        $this->assertNotEmpty($bus->recent('najm_hoda.input.najm_bahar.service.coverage_probe.succeeded', 1));
        $this->assertNotEmpty($bus->recent('najm_hoda.input.group_probe.succeeded', 1));
    }
}

