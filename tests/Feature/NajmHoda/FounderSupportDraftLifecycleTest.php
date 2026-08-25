<?php

namespace Tests\Feature\NajmHoda;

use App\Models\SupportReplyDraft;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\NajmHoda\FounderOps\FounderDraftEditingService;
use App\Services\NajmHoda\FounderOps\FounderSupportDraftApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FounderSupportDraftLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('najm_hoda:autonomy:approval:requests');
    }

    public function test_edited_support_draft_is_frozen_during_approval_and_the_approved_version_is_sent(): void
    {
        $founder = User::factory()->create();
        $member = User::factory()->create();
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids', [$founder->id]);

        $ticket = Ticket::create([
            'user_id' => $member->id,
            'tracking_code' => 'NH-LIFECYCLE-001',
            'subject' => 'نیاز به راهنمایی',
            'message' => 'لطفاً راهنمایی کنید.',
            'status' => 'open',
            'priority' => 'normal',
            'name' => 'Test Member',
            'email' => 'member@example.test',
        ]);

        $draft = SupportReplyDraft::create([
            'ticket_id' => $ticket->id,
            'created_by_user_id' => $founder->id,
            'source' => 'najm_hoda',
            'body' => 'نسخه اولیه پاسخ',
            'status' => 'draft',
            'reason_code' => 'lifecycle-test',
        ]);

        $editing = app(FounderDraftEditingService::class);
        $approval = app(FounderSupportDraftApprovalService::class);

        $editedBody = 'نسخه ویرایش‌شده و مورد نظر مدیرکل';
        $editResult = $editing->updateSupport($draft, $editedBody, $founder->id);

        $this->assertTrue((bool) ($editResult['success'] ?? false), json_encode($editResult, JSON_UNESCAPED_UNICODE));
        $this->assertSame('updated', $editResult['status']);
        $this->assertSame($editedBody, $draft->fresh()->body);

        $prepared = $approval->requestSend($draft->fresh(), $founder->id);
        $this->assertSame('awaiting_approval', $prepared['status'] ?? null, json_encode($prepared, JSON_UNESCAPED_UNICODE));
        $requestId = (string) data_get($prepared, 'approval_request.id');
        $this->assertNotSame('', $requestId);

        $blockedEdit = $editing->updateSupport(
            $draft->fresh(),
            'متنی که نباید پس از درخواست تأیید جایگزین شود',
            $founder->id
        );

        $this->assertFalse((bool) ($blockedEdit['success'] ?? true));
        $this->assertSame('pending_approval_must_be_decided_first', $blockedEdit['reason']);
        $this->assertSame($editedBody, $draft->fresh()->body);

        $result = $approval->decideAndExecute($requestId, 'approve', $founder->id, 'نسخه ویرایش‌شده تأیید شد');

        $this->assertTrue((bool) ($result['success'] ?? false), json_encode($result, JSON_UNESCAPED_UNICODE));
        $this->assertSame('executed', $result['status']);
        $this->assertSame('sent', $draft->fresh()->status);
        $this->assertNotNull($draft->fresh()->approved_at);
        $this->assertNotNull($draft->fresh()->sent_at);
        $this->assertTrue((bool) data_get($result, 'verification.verified'), json_encode($result, JSON_UNESCAPED_UNICODE));
        $this->assertSame('verified', (string) data_get($result, 'verification.status'));

        $commentId = (int) data_get($result, 'result.comment_id', 0);
        $this->assertGreaterThan(0, $commentId);
        $comment = TicketComment::findOrFail($commentId);
        $this->assertSame($editedBody, $comment->message);
        $this->assertSame('support@earthcoop.ir', $comment->user->email);
        $this->assertTrue($comment->user->isSystemIdentity());
        $this->assertNotSame($founder->id, $comment->user_id);
        $this->assertSame('in-progress', $ticket->fresh()->status);
    }

    public function test_non_founder_cannot_edit_support_draft(): void
    {
        $founder = User::factory()->create();
        $other = User::factory()->create();
        $member = User::factory()->create();
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids', [$founder->id]);

        $ticket = Ticket::create([
            'user_id' => $member->id,
            'tracking_code' => 'NH-LIFECYCLE-002',
            'subject' => 'تست اختیار',
            'message' => 'متن تست',
            'status' => 'open',
            'priority' => 'normal',
            'name' => 'Test Member',
            'email' => 'member2@example.test',
        ]);

        $draft = SupportReplyDraft::create([
            'ticket_id' => $ticket->id,
            'created_by_user_id' => $founder->id,
            'source' => 'najm_hoda',
            'body' => 'متن اصلی',
            'status' => 'draft',
            'reason_code' => 'authorization-test',
        ]);

        $result = app(FounderDraftEditingService::class)->updateSupport($draft, 'تغییر غیرمجاز', $other->id);

        $this->assertFalse((bool) ($result['success'] ?? true));
        $this->assertSame('founder_not_authorized', $result['reason']);
        $this->assertSame('متن اصلی', $draft->fresh()->body);
    }
}
