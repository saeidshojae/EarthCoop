<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Announcement;
use App\Models\Group;
use App\Models\Message;
use App\Models\PinnedMessage;
use App\Models\User;
use App\Services\NajmHoda\FounderOps\FounderLowRiskDomainActionService;
use App\Services\Notifications\AnnouncementManagementService;
use App\Services\SystemIdentityService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AnnouncementManagementServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pinned_announcement_is_direct_content_and_uses_management_identity(): void
    {
        $actor = $this->user();
        $group = Group::query()->create([
            'name' => 'Announcement group ' . uniqid('', true),
            'is_open' => 1,
            'location_level' => 'neighborhood',
        ]);

        $management = app(SystemIdentityService::class)->management();
        $announcement = app(AnnouncementManagementService::class)->create([
            'title' => 'اعلان آزمایشی',
            'content' => 'متن اعلان',
            'group_level' => 'neighborhood',
            'should_pin' => true,
        ], $actor->id);

        $pin = PinnedMessage::query()->where('announcement_id', $announcement->id)->firstOrFail();
        $this->assertNull($pin->message_id);
        $this->assertSame(Announcement::class, $pin->content_type);
        $this->assertSame((int) $announcement->id, (int) $pin->content_id);
        $this->assertSame((int) $management->id, (int) $pin->pinned_by);
        $this->assertSame((int) $management->id, (int) $announcement->created_by);
        $this->assertSame(0, Message::query()->where('group_id', $group->id)->count());

        app(AnnouncementManagementService::class)->unpin($announcement);

        $this->assertFalse((bool) $announcement->fresh()->should_pin);
        $this->assertFalse(PinnedMessage::query()->where('announcement_id', $announcement->id)->exists());
    }

    public function test_legacy_synthetic_announcement_pin_is_repaired_without_duplicate_chat_message(): void
    {
        $actor = $this->user();
        $group = Group::query()->create([
            'name' => 'Legacy announcement group ' . uniqid('', true),
            'is_open' => 1,
            'location_level' => 'neighborhood',
        ]);

        $announcement = Announcement::query()->create([
            'title' => 'اطلاعیه قدیمی',
            'content' => 'متن اطلاعیه قدیمی',
            'group_level' => 'neighborhood',
            'should_pin' => true,
            'created_by' => $actor->id,
        ]);
        $message = Message::query()->create([
            'group_id' => $group->id,
            'user_id' => $actor->id,
            'message' => "متن اطلاعیه قدیمی\n\n📷 تصویر اطلاعیه: http://localhost/image.png",
        ]);
        $pin = PinnedMessage::query()->create([
            'message_id' => $message->id,
            'group_id' => $group->id,
            'pinned_by' => $actor->id,
            'announcement_id' => $announcement->id,
        ]);

        $stats = app(AnnouncementManagementService::class)->repairLegacyArtifacts();
        $management = app(SystemIdentityService::class)->management();

        $pin->refresh();
        $this->assertNull($pin->message_id);
        $this->assertSame(Announcement::class, $pin->content_type);
        $this->assertSame((int) $announcement->id, (int) $pin->content_id);
        $this->assertSame((int) $management->id, (int) $pin->pinned_by);
        $this->assertSame((int) $management->id, (int) $announcement->fresh()->created_by);
        $this->assertFalse(Message::query()->whereKey($message->id)->exists());
        $this->assertGreaterThanOrEqual(1, $stats['legacy_pins_repaired']);
        $this->assertGreaterThanOrEqual(1, $stats['legacy_messages_deleted']);
    }

    public function test_group_announcement_partial_renders_media_and_management_label(): void
    {
        $view = file_get_contents(resource_path('views/groups/partials/ann.blade.php'));

        $this->assertStringContainsString('اطلاعیه رسمی', $view);
        $this->assertStringContainsString('تیم مدیریت EarthCoop', $view);
        $this->assertStringContainsString("asset(\$item->image)", $view);
        $this->assertStringContainsString('announcement-', $view);
    }

    public function test_low_risk_notification_action_creates_draft_without_publishing(): void
    {
        $actor = $this->user();
        $before = Announcement::query()->count();

        $result = app(FounderLowRiskDomainActionService::class)->execute('notifications', 'draft_announcement', [
            'title' => 'اعلان پیشنهادی',
            'content' => 'هنوز منتشر نشده',
            'group_level' => 'neighborhood',
            'should_pin' => false,
            'reason_code' => 'announcement-test-' . uniqid(),
            'requested_by' => $actor->id,
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('drafted', $result['status']);
        $this->assertDatabaseHas('founder_announcement_drafts', [
            'id' => $result['draft_id'],
            'status' => 'draft',
            'title' => 'اعلان پیشنهادی',
        ]);
        $this->assertSame($before, Announcement::query()->count());
    }

    private function user(): User
    {
        return User::query()->create([
            'email' => uniqid('announcement-', true) . '@example.test',
            'password' => Hash::make('password'),
            'status' => 1,
            'first_name' => 'Test',
            'last_name' => 'Actor',
            'is_system' => false,
        ]);
    }
}
