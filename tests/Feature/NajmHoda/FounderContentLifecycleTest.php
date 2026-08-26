<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Blog;
use App\Models\Category;
use App\Models\FounderContentDraft;
use App\Models\Group;
use App\Models\User;
use App\Services\NajmHoda\FounderOps\FounderContentDecisionService;
use App\Services\NajmHoda\FounderOps\FounderDraftEditingService;
use App\Services\SystemIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FounderContentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('najm_hoda:autonomy:approval:requests');
    }

    public function test_content_without_category_fails_closed_before_approval(): void
    {
        $founder = User::factory()->create();
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids', [$founder->id]);
        $group = Group::query()->create(['name'=>'Missing category '.uniqid('', true),'is_open'=>1,'location_level'=>'neighborhood']);
        $draft = FounderContentDraft::query()->create([
            'group_id'=>$group->id,'title'=>'عنوان','body'=>'متن','status'=>'draft','reason_code'=>'missing-category-test',
        ]);

        $result = app(FounderContentDecisionService::class)->requestPublish($draft, $founder->id);

        $this->assertFalse((bool)($result['success'] ?? true));
        $this->assertSame('invalid_state', $result['status']);
        $this->assertSame('category_required', $result['reason']);
    }

    public function test_edited_content_is_frozen_then_published_as_management_identity(): void
    {
        $founder = User::factory()->create();
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids', [$founder->id]);
        $category = Category::query()->create(['name'=>'مدیریت و اطلاع‌رسانی']);

        $group = Group::query()->create([
            'name' => 'Founder content lifecycle ' . uniqid('', true),
            'is_open' => 1,
            'location_level' => 'neighborhood',
        ]);

        $draft = FounderContentDraft::query()->create([
            'group_id' => $group->id,
            'category_id' => $category->id,
            'title' => 'عنوان اولیه',
            'body' => 'متن اولیه',
            'status' => 'draft',
            'reason_code' => 'content-lifecycle-test',
        ]);

        $editing = app(FounderDraftEditingService::class);
        $decision = app(FounderContentDecisionService::class);

        $edited = $editing->updateContent(
            $draft,
            'عنوان نهایی مدیریت',
            'متن نهایی ویرایش‌شده مدیریت',
            $founder->id
        );
        $this->assertTrue((bool) ($edited['success'] ?? false), json_encode($edited, JSON_UNESCAPED_UNICODE));

        $prepared = $decision->requestPublish($draft->fresh(), $founder->id);
        $this->assertSame('awaiting_approval', $prepared['status'] ?? null, json_encode($prepared, JSON_UNESCAPED_UNICODE));
        $requestId = (string) data_get($prepared, 'approval_request.id');
        $this->assertNotSame('', $requestId);

        $blocked = $editing->updateContent(
            $draft->fresh(),
            'عنوانی که نباید منتشر شود',
            'متنی که نباید منتشر شود',
            $founder->id
        );
        $this->assertFalse((bool) ($blocked['success'] ?? true));
        $this->assertSame('pending_approval_must_be_decided_first', $blocked['reason']);

        $result = $decision->decideAndExecute($requestId, 'approve', $founder->id, 'محتوا تأیید شد');

        $this->assertTrue((bool) ($result['success'] ?? false), json_encode($result, JSON_UNESCAPED_UNICODE));
        $this->assertSame('executed', $result['status']);
        $this->assertTrue((bool) data_get($result, 'verification.verified'), json_encode($result, JSON_UNESCAPED_UNICODE));

        $blogId = (int) data_get($result, 'result.blog_id', 0);
        $this->assertGreaterThan(0, $blogId);
        $blog = Blog::query()->findOrFail($blogId);
        $management = app(SystemIdentityService::class)->management();

        $this->assertSame('عنوان نهایی مدیریت', $blog->title);
        $this->assertSame('متن نهایی ویرایش‌شده مدیریت', $blog->content);
        $this->assertSame((int) $group->id, (int) $blog->group_id);
        $this->assertSame((int) $category->id, (int) $blog->category_id);
        $this->assertSame((int) $management->id, (int) $blog->user_id);
        $this->assertNotSame((int) $founder->id, (int) $blog->user_id);
        $this->assertSame('published', $draft->fresh()->status);
    }
}
