<?php

namespace Tests\Feature\NajmHoda;

use App\Models\SupportReplyDraft;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Services\NajmHoda\FounderOps\FounderSupportDraftApprovalService;
use App\Services\SystemIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FounderSupportDraftApprovalServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_founder_cannot_decide_support_send_request(): void
    {
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids', [99]);
        $ticket = Ticket::create(['tracking_code'=>'T-1','subject'=>'test','message'=>'body','status'=>'open','priority'=>'normal']);
        $draft = SupportReplyDraft::create(['ticket_id'=>$ticket->id,'source'=>'najm_hoda','body'=>'draft reply','status'=>'draft']);

        $prepared = app(FounderSupportDraftApprovalService::class)->requestSend($draft, 10);
        $requestId = (string) data_get($prepared, 'approval_request.id');
        $result = app(FounderSupportDraftApprovalService::class)->decideAndExecute($requestId, 'approve', 10);

        $this->assertFalse($result['success']);
        $this->assertSame('founder_not_authorized', $result['reason']);
        $this->assertSame('draft', $draft->fresh()->status);
    }

    public function test_rejection_marks_draft_rejected_without_sending(): void
    {
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids', [99]);
        $ticket = Ticket::create(['tracking_code'=>'T-2','subject'=>'test','message'=>'body','status'=>'open','priority'=>'normal']);
        $draft = SupportReplyDraft::create(['ticket_id'=>$ticket->id,'source'=>'najm_hoda','body'=>'draft reply','status'=>'draft']);

        $prepared = app(FounderSupportDraftApprovalService::class)->requestSend($draft, 99);
        $result = app(FounderSupportDraftApprovalService::class)->decideAndExecute((string)data_get($prepared,'approval_request.id'), 'reject', 99);

        $this->assertTrue($result['success']);
        $this->assertSame('rejected', $draft->fresh()->status);
        $this->assertNull($draft->fresh()->sent_at);
    }

    public function test_founder_approval_sends_reply_as_support_identity_updates_ticket_and_marks_draft_sent(): void
    {
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids', [99]);
        $ticket = Ticket::create(['tracking_code'=>'T-3','subject'=>'test','message'=>'body','status'=>'open','priority'=>'normal']);
        $draft = SupportReplyDraft::create(['ticket_id'=>$ticket->id,'source'=>'najm_hoda','body'=>'پاسخ نهایی نجم هدا','status'=>'draft']);

        $prepared = app(FounderSupportDraftApprovalService::class)->requestSend($draft, 99);
        $requestId = (string) data_get($prepared, 'approval_request.id');
        $result = app(FounderSupportDraftApprovalService::class)->decideAndExecute($requestId, 'approve', 99);
        $support = app(SystemIdentityService::class)->support();

        $this->assertTrue($result['success']);
        $this->assertSame('sent', $draft->fresh()->status);
        $this->assertNotNull($draft->fresh()->approved_at);
        $this->assertNotNull($draft->fresh()->sent_at);
        $this->assertSame('in-progress', $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->first_response_at);
        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'user_id' => $support->id,
            'message' => 'پاسخ نهایی نجم هدا',
        ]);
        $this->assertNotSame(99, (int)$support->id);
        $this->assertSame((int)$support->id, (int)data_get($result, 'result.sender_identity_id'));
        $this->assertGreaterThan(0, (int) data_get($result, 'result.comment_id', data_get($result, 'comment_id', 0)));
        $this->assertTrue((bool)data_get($result, 'verification.verified'));
    }

    public function test_approved_support_request_cannot_be_executed_twice(): void
    {
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids', [99]);
        $ticket = Ticket::create(['tracking_code'=>'T-4','subject'=>'test','message'=>'body','status'=>'open','priority'=>'normal']);
        $draft = SupportReplyDraft::create(['ticket_id'=>$ticket->id,'source'=>'najm_hoda','body'=>'یک پاسخ','status'=>'draft']);

        $prepared = app(FounderSupportDraftApprovalService::class)->requestSend($draft, 99);
        $requestId = (string) data_get($prepared, 'approval_request.id');

        $first = app(FounderSupportDraftApprovalService::class)->decideAndExecute($requestId, 'approve', 99);
        $second = app(FounderSupportDraftApprovalService::class)->decideAndExecute($requestId, 'approve', 99);

        $this->assertTrue($first['success']);
        $this->assertFalse($second['success']);
        $this->assertSame('approval_request_not_pending', $second['reason']);
        $this->assertSame(1, TicketComment::query()->where('ticket_id', $ticket->id)->count());
    }
}
