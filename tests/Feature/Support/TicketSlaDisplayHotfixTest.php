<?php

namespace Tests\Feature\Support;

use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketSlaDisplayHotfixTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_ticket_sla_helpers_handle_future_and_expired_deadlines(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-06 12:00:00', 'UTC'));

        $future = new Ticket([
            'status' => 'open',
            'sla_deadline' => now()->addHours(6),
        ]);

        $this->assertFalse($future->isOverdue());
        $this->assertTrue($future->isApproachingDeadline(24));
        $this->assertFalse($future->isApproachingDeadline(4));

        $expired = new Ticket([
            'status' => 'open',
            'sla_deadline' => now()->subMinute(),
        ]);

        $this->assertTrue($expired->isOverdue());
        $this->assertFalse($expired->isApproachingDeadline(24));
    }

    public function test_closed_ticket_is_not_presented_as_currently_overdue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-06 12:00:00', 'UTC'));

        $ticket = new Ticket([
            'status' => 'closed',
            'sla_deadline' => now()->subHour(),
        ]);

        $this->assertFalse($ticket->isOverdue());
        $this->assertFalse($ticket->isApproachingDeadline(24));
    }

    public function test_member_can_open_ticket_show_page_when_sla_deadline_is_present(): void
    {
        $user = User::factory()->create();

        $ticket = Ticket::query()->create([
            'user_id' => $user->id,
            'tracking_code' => 'TK-SLASHOW1',
            'subject' => 'SLA display regression',
            'message' => 'This ticket verifies that the member ticket page renders with SLA enabled.',
            'status' => 'open',
            'priority' => 'normal',
            'category' => 'technical',
            'sla_deadline' => now()->addHours(24),
            'name' => $user->fullName(),
            'email' => $user->email,
        ]);

        $this->actingAs($user)
            ->get(route('user.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('TK-SLASHOW1');
    }
}
