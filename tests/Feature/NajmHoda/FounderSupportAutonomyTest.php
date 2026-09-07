<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Ticket;
use App\Services\NajmHoda\FounderOps\FounderLowRiskDomainActionService;
use App\Services\Support\TicketManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FounderSupportAutonomyTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_management_classifies_without_exposing_content_in_result(): void
    {
        $ticket = Ticket::query()->create([
            'tracking_code' => 'TEST-SEC-1',
            'subject' => 'مشکل امنیت حساب',
            'message' => 'احساس می‌کنم رمز حساب در خطر است',
            'status' => 'open',
        ]);

        $result = app(TicketManagementService::class)->classify($ticket);

        $this->assertSame('security', $result['category']);
        $this->assertArrayNotHasKey('subject', $result);
        $this->assertArrayNotHasKey('message', $result);
        $this->assertSame('security', $ticket->fresh()->category);
    }

    public function test_priority_assignment_is_deterministic_and_bounded(): void
    {
        $ticket = Ticket::query()->create([
            'tracking_code' => 'TEST-URG-1',
            'subject' => 'فوری: مشکل برداشت',
            'message' => 'برداشت من انجام نشده',
            'status' => 'open',
        ]);

        $result = app(TicketManagementService::class)->assignPriority($ticket);

        $this->assertSame('high', $result['priority']);
        $this->assertSame('high', $ticket->fresh()->priority);
    }

    public function test_low_risk_handler_requires_concrete_ticket_id(): void
    {
        $result = app(FounderLowRiskDomainActionService::class)->execute('support', 'classify_ticket', []);

        $this->assertFalse($result['success']);
        $this->assertSame('ticket_not_found', $result['reason']);
    }
}
