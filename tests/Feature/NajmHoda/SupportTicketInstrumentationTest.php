<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Observers\NajmHoda\TicketCommentObserver;
use App\Observers\NajmHoda\TicketObserver;
use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Tests\TestCase;

class SupportTicketInstrumentationTest extends TestCase
{
    public function test_ticket_created_emits_support_input_event(): void
    {
        $bus = new InMemoryRuntimeEventBus(100);
        $this->app->instance(RuntimeEventBus::class, $bus);

        $ticket = new Ticket([
            'user_id' => 42,
            'tracking_code' => 'TK-001',
            'status' => 'open',
            'priority' => 'normal',
        ]);
        $ticket->id = 1001;

        (new TicketObserver())->created($ticket);

        $events = $bus->recent('najm_hoda.input.support.ticket.created', 1);
        $this->assertNotEmpty($events);
        $this->assertSame(1001, (int) data_get($events[0], 'payload.ticket_id'));
        $this->assertSame(42, (int) data_get($events[0], 'payload.user_id'));
        $this->assertSame('support', (string) data_get($events[0], 'payload.scope'));
    }

    public function test_ticket_comment_created_emits_support_comment_event(): void
    {
        $bus = new InMemoryRuntimeEventBus(100);
        $this->app->instance(RuntimeEventBus::class, $bus);

        $comment = new TicketComment([
            'ticket_id' => 1001,
            'user_id' => 7,
            'message' => 'diagnostic message',
        ]);
        $comment->id = 501;

        (new TicketCommentObserver())->created($comment);

        $events = $bus->recent('najm_hoda.input.support.ticket.comment_created', 1);
        $this->assertNotEmpty($events);
        $this->assertSame(1001, (int) data_get($events[0], 'payload.ticket_id'));
        $this->assertSame(501, (int) data_get($events[0], 'payload.comment_id'));
        $this->assertSame(7, (int) data_get($events[0], 'payload.user_id'));
        $this->assertSame('support', (string) data_get($events[0], 'payload.scope'));
    }
}

