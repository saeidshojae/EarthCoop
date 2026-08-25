<?php

namespace Tests\Feature\NajmHoda;

use App\Models\SupportReplyDraft;
use App\Models\Ticket;
use App\Services\NajmHoda\FounderOps\FounderDraftEditingService;
use App\Services\NajmHoda\FounderOps\FounderSupportDraftApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FounderDraftEditingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_founder_can_edit_support_draft_before_requesting_approval(): void
    {
        $ticket = Ticket::create([
            'tracking_code' => 'T-EDIT-1',
            'subject' => 'test',
            'message' => 'body',
            'status' => 'open',
            'priority' => 'normal',
        ]);
        $draft = SupportReplyDraft::create([
            'ticket_id' => $ticket->id,
            'source' => 'najm_hoda',
            'body' => 'متن اولیه نجم هدا',
            'status' => 'draft',
        ]);

        $result = app(FounderDraftEditingService::class)->updateSupport(
            $draft,
            'متن ویرایش‌شده مدیرکل',
            99
        );

        $this->assertTrue($result['success']);
        $this->assertSame('updated', $result['status']);
        $this->assertSame('متن ویرایش‌شده مدیرکل', $draft->fresh()->body);
    }

    public function test_draft_cannot_change_after_approval_request_is_pending(): void
    {
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids', [99]);
        $ticket = Ticket::create([
            'tracking_code' => 'T-EDIT-2',
            'subject' => 'test',
            'message' => 'body',
            'status' => 'open',
            'priority' => 'normal',
        ]);
        $draft = SupportReplyDraft::create([
            'ticket_id' => $ticket->id,
            'source' => 'najm_hoda',
            'body' => 'متن تأییدخواهی‌شده',
            'status' => 'draft',
        ]);

        $prepared = app(FounderSupportDraftApprovalService::class)->requestSend($draft, 99);
        $this->assertSame('awaiting_approval', $prepared['status']);

        $result = app(FounderDraftEditingService::class)->updateSupport(
            $draft,
            'متنی که نباید جایگزین شود',
            99
        );

        $this->assertFalse($result['success']);
        $this->assertSame('pending_approval_must_be_decided_first', $result['reason']);
        $this->assertSame('متن تأییدخواهی‌شده', $draft->fresh()->body);
    }
}
