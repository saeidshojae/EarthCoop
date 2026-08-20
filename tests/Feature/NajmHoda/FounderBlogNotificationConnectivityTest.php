<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\FounderAnnouncementDraft;
use App\Models\FounderContentDraft;
use App\Models\Group;
use App\Models\Message;
use App\Models\PinnedMessage;
use App\Models\User;
use App\Services\NajmHoda\FounderOps\FounderAnnouncementDraftService;
use App\Services\NajmHoda\FounderOps\FounderContentDraftService;
use App\Services\Notifications\AnnouncementManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FounderBlogNotificationConnectivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_announcement_management_keeps_pin_and_generated_message_lifecycle_consistent(): void
    {
        $actor=$this->user();
        $group=Group::query()->create(['name'=>'Announcement target','location_level'=>'7','is_open'=>1]);
        $service=app(AnnouncementManagementService::class);

        $announcement=$service->create([
            'title'=>'اعلان آزمایشی','content'=>'متن اعلان','group_level'=>'7','should_pin'=>true,
        ],$actor->id);

        $this->assertTrue((bool)$announcement->should_pin);
        $this->assertSame(1,PinnedMessage::query()->where('announcement_id',$announcement->id)->count());
        $pin=PinnedMessage::query()->where('announcement_id',$announcement->id)->firstOrFail();
        $this->assertSame($group->id,(int)$pin->group_id);
        $this->assertDatabaseHas('messages',['id'=>$pin->message_id,'group_id'=>$group->id]);

        $messageId=(int)$pin->message_id;
        $service->unpin($announcement);

        $this->assertFalse((bool)$announcement->fresh()->should_pin);
        $this->assertDatabaseMissing('pinned_messages',['announcement_id'=>$announcement->id]);
        $this->assertDatabaseMissing('messages',['id'=>$messageId]);
    }

    public function test_notification_and_blog_drafts_are_persistent_but_do_not_publish_anything(): void
    {
        $actor=$this->user();
        $announcementTitle='پیش نویس اعلان '.uniqid('',true);
        $blogTitle='پیش نویس پست '.uniqid('',true);

        $announcementResult=app(FounderAnnouncementDraftService::class)->draft([
            'title'=>$announcementTitle,'content'=>'فقط پیش نویس','group_level'=>'7','should_pin'=>true,
        ],'announcement:test:'.uniqid('',true),$actor->id);
        $contentResult=app(FounderContentDraftService::class)->draft(
            $blogTitle,'بدنه پست',null,null,'blog:test:'.uniqid('',true),$actor->id
        );

        $this->assertSame('drafted',$announcementResult['status']);
        $this->assertSame('drafted',$contentResult['status']);
        $this->assertSame('draft',FounderAnnouncementDraft::query()->findOrFail($announcementResult['draft_id'])->status);
        $this->assertSame('draft',FounderContentDraft::query()->findOrFail($contentResult['draft_id'])->status);
        $this->assertFalse(Announcement::query()->where('title',$announcementTitle)->exists());
        $this->assertFalse(Blog::query()->where('title',$blogTitle)->exists());
    }

    private function user(): User
    {
        return User::query()->create([
            'email'=>uniqid('founder-content-',true).'@example.test','password'=>bcrypt('password'),
            'status'=>1,'first_name'=>'Founder','last_name'=>'Test','is_system'=>false,
        ]);
    }
}
