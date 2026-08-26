<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Blog;
use App\Models\Category;
use App\Models\Group;
use App\Models\User;
use App\Services\NajmHoda\FounderOps\FounderBlogLifecycleDecisionService;
use App\Services\NajmHoda\FounderOps\FounderExecutiveConnectivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FounderBlogLifecycleDecisionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reject_keeps_post_and_approve_deletes_through_canonical_boundary(): void
    {
        $founder = User::factory()->create(['is_system' => false]);
        $author = User::factory()->create(['is_system' => false]);
        $group = Group::query()->create([
            'group_type' => 'public',
            'name' => 'Founder Ops Blog Group',
            'location_level' => 'neighborhood',
            'is_open' => 1,
        ]);
        $category = Category::query()->create(['name' => 'Founder Ops Blog']);
        config(['najm-hoda-founder-action-policy.founder_approval.user_ids' => [$founder->id]]);

        $makePost = fn (string $title): Blog => Blog::query()->create([
            'title' => $title,
            'content' => 'Body',
            'user_id' => $author->id,
            'group_id' => $group->id,
            'category_id' => $category->id,
        ]);

        $service = app(FounderBlogLifecycleDecisionService::class);

        $keep = $makePost('Keep me');
        $prepared = $service->requestDelete($keep, $founder->id, 'blog-delete-reject-'.$keep->id);
        $requestId = (string) data_get($prepared, 'approval_request.id', '');
        $rejected = $service->decideAndExecute($requestId, 'reject', $founder->id, 'Keep post');

        $this->assertTrue($rejected['success']);
        $this->assertSame('rejected', $rejected['status']);
        $this->assertDatabaseHas('blogs', ['id' => $keep->id]);

        $remove = $makePost('Delete me');
        $prepared = $service->requestDelete($remove, $founder->id, 'blog-delete-approve-'.$remove->id);
        $requestId = (string) data_get($prepared, 'approval_request.id', '');
        $approved = $service->decideAndExecute($requestId, 'approve', $founder->id, 'Founder approved deletion');

        $this->assertTrue($approved['success']);
        $this->assertSame('executed', $approved['status']);
        $this->assertDatabaseMissing('blogs', ['id' => $remove->id]);
    }

    public function test_blog_unpublish_stays_blocked_until_real_publication_state_exists(): void
    {
        $report = app(FounderExecutiveConnectivityService::class)->report();

        $this->assertSame('connected', data_get($report, 'domains.blog.actions.delete_post.state'));
        $this->assertSame('blocked_dependency', data_get($report, 'domains.blog.actions.unpublish_post.state'));
        $this->assertSame(
            'publication_state_missing',
            data_get($report, 'domains.blog.actions.unpublish_post.block.reason')
        );
    }
}
