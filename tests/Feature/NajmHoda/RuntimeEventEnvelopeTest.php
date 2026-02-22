<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use Tests\TestCase;

class RuntimeEventEnvelopeTest extends TestCase
{
    public function test_event_bus_adds_default_event_contract_fields_when_missing(): void
    {
        $bus = new InMemoryRuntimeEventBus(50);
        $bus->emit('najm_hoda.autonomy.executor.executed', [
            'action' => 'run_ops_monitor',
        ]);

        $events = $bus->recent('najm_hoda.autonomy.executor.executed', 1);
        $this->assertNotEmpty($events);

        $payload = $events[0]['payload'] ?? [];
        $this->assertIsArray($payload);
        $this->assertNotSame('', (string) ($payload['request_id'] ?? ''));
        $this->assertNotSame('', (string) ($payload['correlation_id'] ?? ''));
        $this->assertSame('autonomy', (string) ($payload['scope'] ?? ''));
        $this->assertSame('unknown', (string) ($payload['risk'] ?? ''));
        $this->assertSame(1, (int) ($payload['event_version'] ?? 0));
        $this->assertNotSame('', (string) ($payload['emitted_at'] ?? ''));
    }

    public function test_event_bus_preserves_explicit_contract_fields(): void
    {
        $bus = new InMemoryRuntimeEventBus(50);
        $bus->emit('najm_hoda.ops.run.summary', [
            'request_id' => 'req-1',
            'correlation_id' => 'corr-1',
            'actor_id' => '99',
            'scope' => 'ops',
            'risk' => 'low',
            'event_version' => 2,
            'emitted_at' => '2026-02-21T10:00:00Z',
        ]);

        $events = $bus->recent('najm_hoda.ops.run.summary', 1);
        $this->assertNotEmpty($events);

        $payload = $events[0]['payload'] ?? [];
        $this->assertSame('req-1', (string) ($payload['request_id'] ?? ''));
        $this->assertSame('corr-1', (string) ($payload['correlation_id'] ?? ''));
        $this->assertSame('99', (string) ($payload['actor_id'] ?? ''));
        $this->assertSame('ops', (string) ($payload['scope'] ?? ''));
        $this->assertSame('low', (string) ($payload['risk'] ?? ''));
        $this->assertSame(2, (int) ($payload['event_version'] ?? 0));
        $this->assertSame('2026-02-21T10:00:00Z', (string) ($payload['emitted_at'] ?? ''));
    }
}

