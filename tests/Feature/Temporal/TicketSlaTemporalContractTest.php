<?php

namespace Tests\Feature\Temporal;

use App\Models\Ticket;
use App\Services\TicketSlaService;
use Carbon\Carbon;
use Tests\TestCase;

class TicketSlaTemporalContractTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_unsaved_ticket_deadline_falls_back_to_current_instant_when_created_at_was_discarded(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-03 10:00:00', 'UTC'));

        // This mirrors the current ticket creation path: created_at is not mass assignable
        // on an unsaved model, so the SLA service must not dereference null.
        $ticket = new Ticket([
            'priority' => 'normal',
            'created_at' => now(),
        ]);

        $deadline = app(TicketSlaService::class)->calculateDeadline($ticket);

        $this->assertNotNull($deadline);
        $this->assertSame('2026-10-04 10:00:00', $deadline->copy()->utc()->format('Y-m-d H:i:s'));
    }

    public function test_ticket_model_accepts_support_fields_that_creation_paths_persist(): void
    {
        $ticket = new Ticket();

        $this->assertTrue($ticket->isFillable('category'));
        $this->assertTrue($ticket->isFillable('sla_deadline'));
    }
}
