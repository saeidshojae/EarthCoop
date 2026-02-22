<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaCompensatingTransactionService;
use Tests\TestCase;

class CompensatingTransactionServiceTest extends TestCase
{
    public function test_returns_skipped_for_unknown_compensation_type(): void
    {
        $service = new NajmHodaCompensatingTransactionService(new InMemoryRuntimeEventBus(100));

        $result = $service->execute([
            'compensation' => ['type' => 'unknown_comp'],
        ], 'run-1');

        $this->assertFalse((bool) ($result['handled'] ?? true));
        $this->assertSame('skipped', (string) ($result['status'] ?? ''));
    }

    public function test_ticket_revert_fails_when_previous_status_is_missing(): void
    {
        $service = new NajmHodaCompensatingTransactionService(new InMemoryRuntimeEventBus(100));

        $result = $service->execute([
            'compensation' => [
                'type' => 'ticket_status_revert',
                'ticket_id' => 10,
                'use_execution_context_previous_status' => true,
            ],
            'execution_context' => [],
        ], 'run-2');

        $this->assertTrue((bool) ($result['handled'] ?? false));
        $this->assertSame('failed', (string) ($result['status'] ?? ''));
        $this->assertSame('missing_previous_status', (string) ($result['reason'] ?? ''));
    }
}

