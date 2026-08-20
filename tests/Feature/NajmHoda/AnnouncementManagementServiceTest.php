<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Announcement;
use App\Models\Group;
use App\Models\Message;
use App\Models\PinnedMessage;
use App\Models\User;
use App\Services\NajmHoda\FounderOps\FounderLowRiskDomainActionService;
use App\Services\Notifications\AnnouncementManagementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AnnouncementManagementServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pinned_announcement_creates_and_unpin_removes_generated_messages(): void
    {
        $actor=$this->user();
        $group=Group::query()->create([
            'name'=>'Announcement group '.uniqid('',true),
            'is_open'=>1,
            'location_level'=>'neighborhood',
        ]);

        $announcement=app(AnnouncementManagementService::class)->create([
            'title'=>'اعلان آزمایشی',
            'content'=>'متن اعلان',
            'group_level'=>'neighborhood',
            'should_pin'=>true,
        ],$actor->id);

        $pin=PinnedMessage::query()->where('announcement_id',$announcement->id)->firstOrFail();
        $this->assertNotNull($pin->message_id);
        $this->assertTrue(Message::query()->whereKey($pin->message_id)->exists());

        app(AnnouncementManagementService::class)->unpin($announcement);

        $this->assertFalse((bool)$announcement->fresh()->should_pin);
        $this->assertFalse(PinnedMessage::query()->where('announcement_id',$announcement->id)->exists());
        $this->assertFalse(Message::query()->whereKey($pin->message_id)->exists());
    }

    public function test_low_risk_notification_action_creates_draft_without_publishing(): void
    {
        $actor=$this->user();
        $before=Announcement::query()->count();

        $result=app(FounderLowRiskDomainActionService::class)->execute('notifications','draft_announcement',[
            'title'=>'اعلان پیشنهادی',
            'content'=>'هنوز منتشر نشده',
            'group_level'=>'neighborhood',
            'should_pin'=>false,
            'reason_code'=>'announcement-test-'.uniqid(),
            'requested_by'=>$actor->id,
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('drafted',$result['status']);
        $this->assertDatabaseHas('founder_announcement_drafts',[
            'id'=>$result['draft_id'],
            'status'=>'draft',
            'title'=>'اعلان پیشنهادی',
        ]);
        $this->assertSame($before,Announcement::query()->count());
    }

    private function user(): User
    {
        return User::query()->create([
            'email'=>uniqid('announcement-',true).'@example.test',
            'password'=>Hash::make('password'),
            'status'=>1,
            'first_name'=>'Test',
            'last_name'=>'Actor',
            'is_system'=>false,
        ]);
    }
}
