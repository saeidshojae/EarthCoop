<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Category;
use App\Models\FounderContentDraft;
use App\Models\FounderEmailDraft;
use App\Services\NajmHoda\FounderOps\FounderContentDecisionService;
use App\Services\NajmHoda\FounderOps\FounderEmailDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FounderEmailAndContentGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_and_blog_execution_require_founder_approval(): void
    {
        $category = Category::query()->create(['name' => 'مدیریت']);
        $email = FounderEmailDraft::query()->create([
            'recipients' => ['person@example.com'],
            'subject' => 'Test',
            'body' => 'Body',
            'status' => 'draft',
        ]);
        $content = FounderContentDraft::query()->create([
            'content_type' => 'blog_post',
            'group_id' => 1,
            'category_id' => (int) $category->id,
            'title' => 'Test',
            'body' => 'Body',
            'status' => 'draft',
        ]);

        $emailResult = app(FounderEmailDecisionService::class)->requestSend($email, 1);
        $contentResult = app(FounderContentDecisionService::class)->requestPublish($content, 1);

        $this->assertSame('awaiting_approval', $emailResult['status'] ?? null);
        $this->assertSame('awaiting_approval', $contentResult['status'] ?? null);
        $this->assertSame('draft', $email->fresh()->status);
        $this->assertSame('draft', $content->fresh()->status);
    }
}
