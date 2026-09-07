<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Announcement;
use App\Models\FounderAnnouncementDraft;
use App\Models\Group;
use App\Models\Message;
use App\Models\PinnedMessage;
use App\Models\User;
use App\Services\NajmHoda\FounderOps\FounderAnnouncementDecisionService;
use App\Services\NajmHoda\FounderOps\FounderDraftEditingService;
use App\Services\SystemIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FounderAnnouncementLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('najm_hoda:autonomy:approval:requests');
    }

    public function test_edited_pinned_announcement_is_frozen_then_published_as_management_without_synthetic_chat_message(): void
    {
        $founder = User::factory()->create();
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids', [$founder->id]);

        $group = Group::query()->create([
            'name' => 'Founder announcement lifecycle group ' . uniqid('', true),
            'is_open' => 1,
            'location_level' => 'neighborhood',
        ]);

        $draft = FounderAnnouncementDraft::query()->create([
            'title' => 'عنوان اولیه',
            'content' => 'متن اولیه',
            'group_level' => 'neighborhood',
            'image' => 'images/announcements/test-founder-announcement.png',
            'should_pin' => true,
            'status' => 'draft',
            'reason_code' => 'founder-announcement-lifecycle',
        ]);

        $editing = app(FounderDraftEditingService::class);
        $decision = app(FounderAnnouncementDecisionService::class);

        $edit = $editing->updateAnnouncement(
            $draft,
            'عنوان ویرایش‌شده مدیرکل',
            'متن ویرایش‌شده و نهایی اطلاعیه',
            $founder->id
        );

        $this->assertTrue((bool) ($edit['success'] ?? false), json_encode($edit, JSON_UNESCAPED_UNICODE));
        $this->assertSame('updated', $edit['status']);

        $prepared = $decision->requestPublish($draft->fresh(), $founder->id);
        $this->assertSame('awaiting_approval', $prepared['status'] ?? null, json_encode($prepared, JSON_UNESCAPED_UNICODE));
        $requestId = (string) data_get($prepared, 'approval_request.id');
        $this->assertNotSame('', $requestId);

        $blocked = $editing->updateAnnouncement(
            $draft->fresh(),
            'عنوانی که نباید اعمال شود',
            'متنی که نباید اعمال شود',
            $founder->id
        );
        $this->assertFalse((bool) ($blocked['success'] ?? true));
        $this->assertSame('pending_approval_must_be_decided_first', $blocked['reason']);

        $result = $decision->decideAndExecute($requestId, 'approve', $founder->id, 'انتشار اطلاعیه تأیید شد');

        $this->assertTrue((bool) ($result['success'] ?? false), json_encode($result, JSON_UNESCAPED_UNICODE));
        $this->assertSame('executed', $result['status']);
        $this->assertTrue((bool) data_get($result, 'verification.verified'), json_encode($result, JSON_UNESCAPED_UNICODE));

        $announcementId = (int) data_get($result, 'result.announcement_id', 0);
        $this->assertGreaterThan(0, $announcementId);
        $announcement = Announcement::query()->findOrFail($announcementId);
        $management = app(SystemIdentityService::class)->management();

        $this->assertSame('عنوان ویرایش‌شده مدیرکل', $announcement->title);
        $this->assertSame('متن ویرایش‌شده و نهایی اطلاعیه', $announcement->content);
        $this->assertSame('images/announcements/test-founder-announcement.png', $announcement->image);
        $this->assertTrue((bool) $announcement->should_pin);
        $this->assertSame((int) $management->id, (int) $announcement->created_by);
        $this->assertNotSame((int) $founder->id, (int) $announcement->created_by);

        $pin = PinnedMessage::query()
            ->where('group_id', $group->id)
            ->where('announcement_id', $announcement->id)
            ->firstOrFail();
        $this->assertNull($pin->message_id);
        $this->assertSame(Announcement::class, $pin->content_type);
        $this->assertSame((int) $announcement->id, (int) $pin->content_id);
        $this->assertSame((int) $management->id, (int) $pin->pinned_by);
        $this->assertSame(0, Message::query()->where('group_id', $group->id)->count());

        $this->assertSame('published', $draft->fresh()->status);
        $this->assertSame((int) $announcement->id, (int) $draft->fresh()->announcement_id);
    }
}
