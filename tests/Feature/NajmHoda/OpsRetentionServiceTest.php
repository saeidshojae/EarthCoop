<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaOpsRetentionService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OpsRetentionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.ops.monitor.summary_ttl_minutes' => 180,
            'najm-hoda.runtime.ops.monitor.summary_history_size' => 2,
            'najm-hoda.runtime.ops.retention.telemetry_index_retention_hours' => 1,
            'najm-hoda.runtime.ops.retention.telemetry_index_max_size' => 100,
        ]);

        Cache::flush();
    }

    public function test_retention_prunes_summary_history_and_stale_telemetry_keys(): void
    {
        Cache::put('najm_hoda:ops:run_summary_history', [
            ['run_id' => 'r1'],
            ['run_id' => 'r2'],
            ['run_id' => 'r3'],
        ], now()->addMinutes(180));

        Cache::put('telemetry:fresh', 10, now()->addHours(2));
        Cache::put('telemetry:stale', 5, now()->addHours(2));

        $now = time();
        Cache::put('najm_hoda:ops:telemetry:index', [
            ['key' => 'telemetry:stale', 'created_at' => $now - 7200],
            ['key' => 'telemetry:fresh', 'created_at' => $now],
        ], now()->addHours(4));

        $service = new NajmHodaOpsRetentionService(new InMemoryRuntimeEventBus(200));
        $result = $service->prune();

        $history = Cache::get('najm_hoda:ops:run_summary_history');
        $this->assertIsArray($history);
        $this->assertCount(2, $history);
        $this->assertSame('r1', $history[0]['run_id']);
        $this->assertSame('r2', $history[1]['run_id']);

        $this->assertNull(Cache::get('telemetry:stale'));
        $this->assertSame(10, Cache::get('telemetry:fresh'));

        $this->assertSame(1, (int) ($result['history_trimmed'] ?? 0));
        $this->assertSame(1, (int) ($result['telemetry_keys_pruned'] ?? 0));
    }
}

