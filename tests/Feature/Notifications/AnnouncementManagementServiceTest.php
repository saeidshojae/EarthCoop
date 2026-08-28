<?php

namespace Tests\Feature\Notifications;

use App\Models\Announcement;
use App\Models\Group;
use App\Models\Message;
use App\Models\PinnedMessage;
use App\Services\Notifications\AnnouncementManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pinned_announcement_is_direct_content_not_a_fake_founder_chat_message(): void
    {
        $group = Group::query()->create([
            'name' => 'Announcement target',
            'location_level' => 'neighborhood',
        ]);

        $announcement = app(AnnouncementManagementService::class)->create([
            'title' => 'آزمایش',
            'content' => 'تست اطلاعیه',
            'group_level' => 'neighborhood',
            'image' => '/images/announcements/test.png',
            'should_pin' => true,
        ], 999999);

        $management = $announcement->creator;
        $this->assertNotNull($management);
        $this->assertTrue($management->isSystemIdentity());
        $this->assertSame('management@earthcoop.ir', $management->email);
        $this->assertSame('تیم مدیریت', $management->first_name);

        $pin = PinnedMessage::query()->where('group_id', $group->id)->firstOrFail();
        $this->assertSame(Announcement::class, $pin->content_type);
        $this->assertSame($announcement->id, (int) $pin->content_id);
        $this->assertSame($announcement->id, (int) $pin->announcement_id);
        $this->assertNull($pin->message_id);
        $this->assertSame($management->id, (int) $pin->pinned_by);
        $this->assertSame(0, Message::query()->where('group_id', $group->id)->count());
    }

    public function test_updating_legacy_pinned_announcement_removes_generated_chat_message(): void
    {
        $group = Group::query()->create([
            'name' => 'Legacy announcement target',
            'location_level' => 'neighborhood',
        ]);
        $management = app(\App\Services\SystemIdentityService::class)->management();
        $announcement = Announcement::query()->create([
            'title' => 'قدیمی',
            'content' => 'متن قدیمی',
            'group_level' => 'neighborhood',
            'should_pin' => true,
            'created_by' => $management->id,
        ]);
        $legacyMessage = Message::query()->create([
            'group_id' => $group->id,
            'user_id' => $management->id,
            'message' => "متن قدیمی\n\n📷 تصویر اطلاعیه: http://example.test/old.png",
        ]);
        PinnedMessage::query()->create([
            'message_id' => $legacyMessage->id,
            'group_id' => $group->id,
            'pinned_by' => $management->id,
            'announcement_id' => $announcement->id,
            'content_type' => \App\Models\Message::class,
            'content_id' => $legacyMessage->id,
        ]);

        app(AnnouncementManagementService::class)->update($announcement, [
            'title' => 'جدید',
            'content' => 'متن جدید',
            'group_level' => 'neighborhood',
            'image' => '/images/announcements/new.png',
            'should_pin' => true,
        ], 123);

        $this->assertDatabaseMissing('messages', ['id' => $legacyMessage->id]);
        $this->assertDatabaseHas('pinned_messages', [
            'group_id' => $group->id,
            'announcement_id' => $announcement->id,
            'content_type' => Announcement::class,
            'content_id' => $announcement->id,
            'message_id' => null,
        ]);
    }
}
