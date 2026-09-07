<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Blog;
use App\Models\Category;
use App\Models\Group;
use App\Models\User;
use App\Services\NajmHoda\FounderOps\FounderExecutiveConnectivityService;
use App\Services\NajmHoda\FounderOps\FounderLowRiskDomainActionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FounderBlogConnectivityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_blog_draft_and_edit_suggestion_are_connected_without_mutating_published_content(): void
    {
        $actor=$this->user();
        $group=Group::query()->create(['name'=>'Blog connectivity '.uniqid('',true),'is_open'=>1]);
        $category=Category::query()->create(['name'=>'Najm Hoda connectivity '.uniqid('',true)]);
        $service=app(FounderLowRiskDomainActionService::class);

        $draft=$service->execute('blog','draft_post',[
            'title'=>'پیش‌نویس نجم هدا',
            'body'=>'متن پیش‌نویس',
            'group_id'=>$group->id,
            'category_id'=>$category->id,
            'reason_code'=>'blog-draft-'.uniqid(),
            'requested_by'=>$actor->id,
        ]);

        $this->assertTrue($draft['success']);
        $this->assertContains($draft['status'],['drafted','already_prepared']);
        $this->assertDatabaseHas('founder_content_drafts',[
            'id'=>$draft['draft_id'],
            'status'=>'draft',
            'title'=>'پیش‌نویس نجم هدا',
        ]);

        $blog=Blog::query()->create([
            'title'=>'عنوان فعلی',
            'content'=>'متن فعلی',
            'group_id'=>$group->id,
            'category_id'=>$category->id,
            'user_id'=>$actor->id,
        ]);

        $suggestion=$service->execute('blog','suggest_edit',[
            'entity_id'=>$blog->id,
            'suggested_title'=>'عنوان پیشنهادی',
            'suggested_body'=>'متن پیشنهادی',
            'reason_code'=>'blog-edit-'.uniqid(),
        ]);

        $this->assertTrue($suggestion['success']);
        $this->assertSame('عنوان پیشنهادی',$suggestion['proposal']['suggested_title']);
        $this->assertSame('عنوان فعلی',$blog->fresh()->title);
        $this->assertSame('متن فعلی',$blog->fresh()->content);
    }

    public function test_connectivity_report_exposes_connected_actions_and_remaining_gaps(): void
    {
        $report=app(FounderExecutiveConnectivityService::class)->report();

        $this->assertSame('partial',$report['domains']['blog']['stage']);
        $this->assertSame('partial',$report['domains']['notifications']['stage']);

        $this->assertSame('connected',$report['domains']['blog']['actions']['draft_post']['state']);
        $this->assertSame('connected',$report['domains']['blog']['actions']['suggest_edit']['state']);
        $this->assertSame('connected',$report['domains']['blog']['actions']['publish_post']['state']);
        $this->assertSame('blocked_dependency',$report['domains']['blog']['actions']['unpublish_post']['state']);
        $this->assertSame('connected',$report['domains']['blog']['actions']['delete_post']['state']);

        $this->assertSame('connected',$report['domains']['notifications']['actions']['draft_announcement']['state']);
        $this->assertSame('connected',$report['domains']['notifications']['actions']['publish_announcement']['state']);
        $this->assertSame('blocked_dependency',$report['domains']['notifications']['actions']['change_global_notification_defaults']['state']);
    }

    private function user(): User
    {
        return User::query()->create([
            'email'=>uniqid('blog-connectivity-',true).'@example.test',
            'password'=>Hash::make('password'),
            'status'=>1,
            'first_name'=>'Test',
            'last_name'=>'Actor',
            'is_system'=>false,
        ]);
    }
}
