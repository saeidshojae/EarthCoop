<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaRunbookRegistryService;
use Tests\TestCase;

class RunbookRegistryServiceTest extends TestCase
{
    public function test_runbook_registry_and_readiness_report(): void
    {
        config([
            'najm-hoda.runtime.autonomy.runbooks.min_required_checklist_items' => 3,
            'najm-hoda.runtime.autonomy.runbooks.registry' => [
                [
                    'id' => 'incident_response',
                    'title' => 'Incident Response',
                    'owner' => 'SRE',
                    'version' => '1.0.0',
                    'status' => 'active',
                    'checklist' => ['a', 'b', 'c'],
                ],
                [
                    'id' => 'degraded_mode',
                    'title' => 'Degraded Mode',
                    'owner' => 'Platform',
                    'version' => '1.0.0',
                    'status' => 'draft',
                    'checklist' => ['a', 'b', 'c', 'd'],
                ],
            ],
        ]);

        $bus = new InMemoryRuntimeEventBus(100);
        $service = new NajmHodaRunbookRegistryService($bus);

        $registry = $service->all();
        $this->assertCount(2, $registry);
        $this->assertSame('incident_response', (string) ($registry[0]['id'] ?? ''));

        $readiness = $service->readiness();
        $this->assertSame(2, (int) ($readiness['total_runbooks'] ?? 0));
        $this->assertSame(1, (int) ($readiness['ready_runbooks'] ?? 0));
        $this->assertSame(0.5, (float) ($readiness['readiness_ratio'] ?? 0.0));
        $this->assertSame('breach', (string) ($readiness['status'] ?? ''));

        $events = $bus->recent('najm_hoda.autonomy.runbook.readiness.reported', 1);
        $this->assertCount(1, $events);
    }
}
