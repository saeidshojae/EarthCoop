<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Category;
use App\Models\FounderContentDraft;
use App\Models\SupportReplyDraft;
use App\Models\Ticket;
use App\Services\NajmHoda\FounderOps\FounderDraftEditingService;
use App\Services\NajmHoda\FounderOps\FounderSupportDraftApprovalService;
use App\Services\NajmHoda\Runtime\RuntimeEventBus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FounderDraftEditingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_founder_can_edit_support_draft_before_requesting_approval_and_edit_is_audited(): void
    {
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids', [99]);
        app(RuntimeEventBus::class)->clear();
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

        $events = app(RuntimeEventBus::class)->recent('najm_hoda.founder_ops.draft.edited', 5);
        $this->assertTrue($result['success']);
        $this->assertSame('updated', $result['status']);
        $this->assertSame('متن ویرایش‌شده مدیرکل', $draft->fresh()->body);
        $this->assertNotEmpty($events);
        $this->assertSame(99, (int) data_get($events[0], 'payload.edited_by'));
        $this->assertSame(['body'], data_get($events[0], 'payload.changed_fields'));
    }

    public function test_founder_can_set_content_category_before_approval(): void
    {
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids', [99]);
        app(RuntimeEventBus::class)->clear();
        $category = Category::query()->create(['name' => 'مدیریت']);
        $draft = FounderContentDraft::query()->create([
            'title' => 'عنوان اولیه',
            'body' => 'متن اولیه',
            'status' => 'draft',
        ]);

        $result = app(FounderDraftEditingService::class)->updateContent(
            $draft,
            'عنوان ویرایش‌شده',
            'متن ویرایش‌شده',
            99,
            (int) $category->id
        );

        $this->assertTrue($result['success']);
        $this->assertSame((int)$category->id, (int)$draft->fresh()->category_id);
        $this->assertContains('category_id', (array)($result['changed_fields'] ?? []));
    }

    public function test_non_founder_cannot_edit_draft(): void
    {
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids', [99]);
        $ticket = Ticket::create([
            'tracking_code' => 'T-EDIT-NO',
            'subject' => 'test',
            'message' => 'body',
            'status' => 'open',
            'priority' => 'normal',
        ]);
        $draft = SupportReplyDraft::create([
            'ticket_id' => $ticket->id,
            'source' => 'najm_hoda',
            'body' => 'متن اصلی',
            'status' => 'draft',
        ]);

        $result = app(FounderDraftEditingService::class)->updateSupport($draft, 'ویرایش غیرمجاز', 10);

        $this->assertFalse($result['success']);
        $this->assertSame('founder_not_authorized', $result['reason']);
        $this->assertSame('متن اصلی', $draft->fresh()->body);
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
