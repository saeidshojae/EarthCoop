<?php

namespace Tests\Feature\NajmHoda;

use App\Models\SupportReplyDraft;
use App\Models\Ticket;
use App\Services\NajmHoda\FounderOps\FounderSupportDraftApprovalService;
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
}
