<?php

namespace Tests\Feature\Support;

use App\Models\Ticket;
use App\Services\TicketSlaService;
use Carbon\Carbon;
use Tests\TestCase;

class TicketSlaHotfixTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_unsaved_ticket_deadline_uses_current_time_when_created_at_is_not_yet_available(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-06 10:00:00', 'UTC'));

        $ticket = new Ticket([
            'priority' => 'normal',
            'created_at' => now(),
        ]);

        $this->assertNull($ticket->created_at);

        $deadline = app(TicketSlaService::class)->calculateDeadline($ticket);

        $this->assertNotNull($deadline);
        $this->assertSame('2026-10-07 10:00:00', $deadline->copy()->utc()->format('Y-m-d H:i:s'));
    }

    public function test_ticket_model_accepts_fields_written_by_ticket_creation_flows(): void
    {
        $ticket = new Ticket();

        $this->assertTrue($ticket->isFillable('category'));
        $this->assertTrue($ticket->isFillable('sla_deadline'));
    }
}
