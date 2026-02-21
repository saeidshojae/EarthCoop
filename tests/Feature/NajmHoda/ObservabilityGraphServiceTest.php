<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaObservabilityGraphService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ObservabilityGraphServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.autonomy.observability.event_limit' => 200,
            'najm-hoda.runtime.autonomy.observability.window_hours' => 24,
            'najm-hoda.runtime.autonomy.observability.snapshot_ttl_minutes' => 120,
        ]);

        Cache::flush();
    }

    public function test_observability_snapshot_includes_runtime_and_module_context(): void
    {
        $bus = new InMemoryRuntimeEventBus(200);
        $bus->emit('najm_hoda.request.received', ['request_id' => '1']);
        $bus->emit('najm_hoda.response.ready', ['request_id' => '1']);
        $bus->emit('najm_hoda.request.received', ['request_id' => '2']);
        $bus->emit('najm_hoda.response.failed', ['request_id' => '2']);

        $service = new NajmHodaObservabilityGraphService($bus);
        $snapshot = $service->snapshot(200);

        $this->assertIsArray($snapshot);
        $this->assertArrayHasKey('runtime', $snapshot);
        $this->assertArrayHasKey('modules', $snapshot);
        $this->assertSame(50.0, (float) ($snapshot['error_rate_percent'] ?? 0.0));

        $cached = Cache::get('najm_hoda:autonomy:last_observability_snapshot');
        $this->assertIsArray($cached);
        $this->assertSame((float) ($snapshot['error_rate_percent'] ?? 0.0), (float) ($cached['error_rate_percent'] ?? -1));

        $events = $bus->recent('najm_hoda.autonomy.observability.snapshot', 1);
        $this->assertNotEmpty($events);
    }
}
