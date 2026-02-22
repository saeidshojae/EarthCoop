<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CoverageKpiCommandSustainmentTest extends TestCase
{
    public function test_command_can_pass_sustainment_with_heartbeat_path(): void
    {
        config([
            'najm-hoda.enabled' => true,
            'najm-hoda.runtime.coverage_kpi.sustainment.required_consecutive_ok' => 1,
            'najm-hoda.runtime.coverage_kpi.sustainment.require_without_probe' => true,
            'najm-hoda.runtime.coverage_kpi.heartbeat.enabled' => true,
        ]);

        $bus = new InMemoryRuntimeEventBus(500);
        $this->app->instance(RuntimeEventBus::class, $bus);

        $exit = Artisan::call('najm-hoda:coverage-kpi', [
            '--window' => 24,
            '--limit' => 5000,
            '--heartbeat' => true,
            '--require-sustained' => true,
        ]);

        $this->assertSame(0, $exit);
    }
}

