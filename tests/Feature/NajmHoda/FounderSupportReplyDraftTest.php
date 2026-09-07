<?php

namespace Tests\Feature\NajmHoda;

use App\Models\SupportReplyDraft;
use App\Models\Ticket;
use App\Services\NajmHoda\Agents\StewardAgent;
use App\Services\Support\TicketReplyDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FounderSupportReplyDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_open_draft_is_reused_without_calling_agent(): void
    {
        $ticket = Ticket::create([
            'tracking_code' => 'TK-DRAFT1',
            'subject' => 'مشکل ورود',
            'message' => 'نمی‌توانم وارد شوم',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $draft = SupportReplyDraft::create([
            'ticket_id' => $ticket->id,
            'source' => 'najm_hoda',
            'body' => 'پاسخ پیشنهادی موجود',
            'status' => 'draft',
        ]);

        $agent = Mockery::mock(StewardAgent::class);
        $agent->shouldNotReceive('ask');
        $this->app->instance(StewardAgent::class, $agent);

        $result = app(TicketReplyDraftService::class)->generate($ticket);

        $this->assertTrue($result['success']);
        $this->assertSame('existing', $result['mode']);
        $this->assertSame($draft->id, $result['draft_id']);
    }

    public function test_inactive_ticket_is_not_drafted(): void
    {
        $ticket = Ticket::create([
            'tracking_code' => 'TK-DRAFT2',
            'subject' => 'موضوع بسته',
            'message' => 'حل شده',
            'status' => 'closed',
            'priority' => 'normal',
        ]);

        $result = app(TicketReplyDraftService::class)->generate($ticket);

        $this->assertFalse($result['success']);
        $this->assertSame('ticket_not_active', $result['reason']);
        $this->assertDatabaseCount('support_reply_drafts', 0);
    }
}
