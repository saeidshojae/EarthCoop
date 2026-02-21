<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaAutonomyAuditService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AutonomyAuditServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.autonomy.audit.history_size' => 100,
            'najm-hoda.runtime.autonomy.audit.retention_minutes' => 120,
        ]);
        Cache::flush();
    }

    public function test_record_and_replay_trace_flow(): void
    {
        $bus = new InMemoryRuntimeEventBus(200);
        $service = new NajmHodaAutonomyAuditService($bus);

        $trace = $service->record([
            'run_id' => 'run-123',
            'executed' => true,
            'status' => 'completed',
            'goals' => ['stabilize_operations'],
            'plan' => [['action' => 'run_ops_monitor']],
            'execution_results' => [['action' => 'run_ops_monitor', 'status' => 'executed']],
            'generated_at' => now()->toIso8601String(),
        ]);

        $this->assertSame('run-123', (string) ($trace['run_id'] ?? ''));
        $history = $service->history(10);
        $this->assertNotEmpty($history);
        $this->assertSame('run-123', (string) ($history[0]['run_id'] ?? ''));

        $replay = $service->replay('run-123');
        $this->assertTrue((bool) ($replay['success'] ?? false));
        $this->assertSame('run-123', (string) ($replay['run_id'] ?? ''));

        $events = $bus->recent('najm_hoda.autonomy.audit.replayed', 1);
        $this->assertNotEmpty($events);
    }
}
