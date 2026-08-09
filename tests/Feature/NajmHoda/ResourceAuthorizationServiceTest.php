<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Ticket;
use App\Models\User;
use App\Services\NajmHoda\Runtime\NajmHodaResourceAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceAuthorizationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_owner_can_use_ticket_capability(): void
    {
        $owner = User::factory()->create();
        $ticket = $this->ticketFor($owner);

        $result = (new NajmHodaResourceAuthorizationService())->authorize(
            $owner->id,
            'set_ticket_needs_review',
            ['ticket_id' => $ticket->id]
        );

        $this->assertTrue($result['allowed']);
        $this->assertSame('ticket_owner', $result['reason']);
    }

    public function test_ticket_assignee_can_use_ticket_capability(): void
    {
        $owner = User::factory()->create();
        $assignee = User::factory()->create();
        $ticket = $this->ticketFor($owner, $assignee);

        $result = (new NajmHodaResourceAuthorizationService())->authorize(
            $assignee->id,
            'set_ticket_needs_review',
            ['ticket_id' => $ticket->id]
        );

        $this->assertTrue($result['allowed']);
        $this->assertSame('ticket_assignee', $result['reason']);
    }

    public function test_unrelated_user_cannot_use_ticket_capability(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $ticket = $this->ticketFor($owner);

        $result = (new NajmHodaResourceAuthorizationService())->authorize(
            $stranger->id,
            'set_ticket_needs_review',
            ['ticket_id' => $ticket->id]
        );

        $this->assertFalse($result['allowed']);
        $this->assertSame('resource_not_accessible', $result['reason']);
    }

    public function test_ticket_capability_requires_real_actor(): void
    {
        $result = (new NajmHodaResourceAuthorizationService())->authorize(
            null,
            'set_ticket_needs_review',
            ['ticket_id' => 1]
        );

        $this->assertFalse($result['allowed']);
        $this->assertSame('actor_required', $result['reason']);
    }

    protected function ticketFor(User $owner, ?User $assignee = null): Ticket
    {
        return Ticket::query()->create([
            'user_id' => $owner->id,
            'tracking_code' => 'TK-' . strtoupper(substr(md5((string) microtime(true) . random_int(1, 999999)), 0, 8)),
            'subject' => 'Authorization test',
            'message' => 'Test ticket for Najm Hoda resource authorization.',
            'status' => 'open',
            'email' => $owner->email,
            'assignee_id' => $assignee?->id,
        ]);
    }
}
