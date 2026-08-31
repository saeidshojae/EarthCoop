<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Announcement;
use App\Models\Blog;
use App\Models\Category;
use App\Models\FounderAnnouncementDraft;
use App\Models\FounderContentDraft;
use App\Models\Group;
use App\Models\User;
use App\Services\NajmHoda\FounderOps\FounderAnnouncementDecisionService;
use App\Services\NajmHoda\FounderOps\FounderContentDecisionService;
use App\Services\NajmHoda\FounderOps\FounderDraftEditingService;
use App\Services\SystemIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FounderPublicationDraftLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('najm_hoda:autonomy:approval:requests');
    }

    public function test_edited_content_draft_is_frozen_and_exact_approved_version_is_published(): void
    {
        $founder = User::factory()->create();
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids', [$founder->id]);
        $category = Category::query()->create(['name'=>'محتوای مدیریت']);

        $group = Group::query()->create([
            'group_type' => 'public',
            'name' => 'گروه آزمون انتشار',
            'location_level' => 'neighborhood',
            'is_open' => 1,
        ]);

        $draft = FounderContentDraft::query()->create([
            'content_type' => 'blog_post',
            'group_id' => $group->id,
            'category_id' => $category->id,
            'title' => 'عنوان اولیه',
            'body' => '<p>متن اولیه</p>',
            'status' => 'draft',
            'reason_code' => 'content-lifecycle-test',
            'created_by' => $founder->id,
        ]);

        $editing = app(FounderDraftEditingService::class);
        $decision = app(FounderContentDecisionService::class);

        $editedTitle = 'عنوان نهایی مدیرکل';
        $editedBody = '<p>متن نهایی مدیرکل</p>';
        $edited = $editing->updateContent($draft, $editedTitle, $editedBody, $founder->id);

        $this->assertTrue((bool) ($edited['success'] ?? false));
        $this->assertSame('updated', $edited['status']);
        $this->assertSame($editedTitle, $draft->fresh()->title);
        $this->assertSame($editedBody, $draft->fresh()->body);

        $prepared = $decision->requestPublish($draft->fresh(), $founder->id);
        $this->assertSame('awaiting_approval', $prepared['status'] ?? null);
        $requestId = (string) data_get($prepared, 'approval_request.id');
        $this->assertNotSame('', $requestId);

        $blocked = $editing->updateContent(
            $draft->fresh(),
            'عنوانی که نباید منتشر شود',
            '<p>متنی که نباید منتشر شود</p>',
            $founder->id
        );
        $this->assertFalse((bool) ($blocked['success'] ?? true));
        $this->assertSame('pending_approval_must_be_decided_first', $blocked['reason']);

        $result = $decision->decideAndExecute($requestId, 'approve', $founder->id, 'نسخه نهایی محتوا تأیید شد');

        $this->assertTrue((bool) ($result['success'] ?? false));
        $this->assertSame('executed', $result['status']);
        $this->assertSame('published', $draft->fresh()->status);
        $this->assertSame($founder->id, (int) $draft->fresh()->approved_by);
        $this->assertNotNull($draft->fresh()->published_at);
        $this->assertTrue((bool) data_get($result, 'verification.verified'));

        $blogId = (int) data_get($result, 'result.blog_id', 0);
        $blog = Blog::query()->findOrFail($blogId);
        $management = app(SystemIdentityService::class)->management();
        $this->assertSame($editedTitle, $blog->title);
        $this->assertSame($editedBody, $blog->content);
        $this->assertSame($group->id, (int) $blog->group_id);
        $this->assertSame((int)$category->id, (int)$blog->category_id);
        $this->assertSame((int)$management->id, (int)$blog->user_id);
        $this->assertNotSame((int)$founder->id, (int)$blog->user_id);
    }

    public function test_edited_announcement_draft_is_frozen_and_exact_approved_version_is_published(): void
    {
        Cache::forget('najm_hoda:autonomy:approval:requests');

        $founder = User::factory()->create();
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids', [$founder->id]);

        $draft = FounderAnnouncementDraft::query()->create([
            'title' => 'اطلاعیه اولیه',
            'content' => 'متن اولیه اطلاعیه',
            'group_level' => 'neighborhood',
            'should_pin' => false,
            'status' => 'draft',
            'reason_code' => 'announcement-lifecycle-test',
            'created_by' => $founder->id,
        ]);

        $editing = app(FounderDraftEditingService::class);
        $decision = app(FounderAnnouncementDecisionService::class);

        $editedTitle = 'اطلاعیه نهایی مدیرکل';
        $editedContent = 'متن نهایی و تأییدشدنی اطلاعیه';
        $edited = $editing->updateAnnouncement($draft, $editedTitle, $editedContent, $founder->id);

        $this->assertTrue((bool) ($edited['success'] ?? false));
        $this->assertSame('updated', $edited['status']);
        $this->assertSame($editedTitle, $draft->fresh()->title);
        $this->assertSame($editedContent, $draft->fresh()->content);

        $prepared = $decision->requestPublish($draft->fresh(), $founder->id);
        $this->assertSame('awaiting_approval', $prepared['status'] ?? null);
        $requestId = (string) data_get($prepared, 'approval_request.id');
        $this->assertNotSame('', $requestId);

        $blocked = $editing->updateAnnouncement(
            $draft->fresh(),
            'اطلاعیه‌ای که نباید منتشر شود',
            'متنی که نباید منتشر شود',
            $founder->id
        );
        $this->assertFalse((bool) ($blocked['success'] ?? true));
        $this->assertSame('pending_approval_must_be_decided_first', $blocked['reason']);

        $result = $decision->decideAndExecute($requestId, 'approve', $founder->id, 'نسخه نهایی اطلاعیه تأیید شد');

        $this->assertTrue((bool) ($result['success'] ?? false));
        $this->assertSame('executed', $result['status']);
        $this->assertSame('published', $draft->fresh()->status);
        $this->assertSame($founder->id, (int) $draft->fresh()->approved_by);
        $this->assertNotNull($draft->fresh()->published_at);
        $this->assertTrue((bool) data_get($result, 'verification.verified'));

        $announcementId = (int) data_get($result, 'result.announcement_id', 0);
        $announcement = Announcement::query()->findOrFail($announcementId);
        $management = app(SystemIdentityService::class)->management();
        $this->assertSame($editedTitle, $announcement->title);
        $this->assertSame($editedContent, $announcement->content);
        $this->assertSame('neighborhood', $announcement->group_level);
        $this->assertSame((int)$management->id, (int)$announcement->created_by);
        $this->assertNotSame((int)$founder->id, (int)$announcement->created_by);
        $this->assertSame($announcement->id, (int) $draft->fresh()->announcement_id);
    }
}
