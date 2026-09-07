<?php

namespace Tests\Feature\NajmHoda;

use App\Models\FaqQuestion;
use App\Models\Page;
use App\Services\Content\ContentManagementService;
use App\Services\NajmHoda\FounderOps\FounderContentLifecycleDecisionService;
use App\Services\NajmHoda\FounderOps\FounderLowRiskDomainActionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;

class ContentManagementServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_low_risk_page_draft_does_not_mutate_page(): void
    {
        $page = Page::query()->create([
            'title' => 'Current title',
            'slug' => 'current-title-' . uniqid(),
            'template' => 'default',
            'content' => 'Current body',
            'is_published' => false,
            'show_in_header' => false,
        ]);

        $result = app(FounderLowRiskDomainActionService::class)->execute('content', 'draft_page_update', [
            'entity_id' => $page->id,
            'changes' => ['title'=>'Proposed title','content'=>'Proposed body','is_published'=>true],
            'reason_code' => 'page-draft-' . uniqid(),
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('drafted', $result['status']);
        $this->assertSame('Proposed title', $result['proposal']['title']);
        $this->assertArrayNotHasKey('is_published', $result['proposal']);
        $this->assertSame('Current title', $page->fresh()->title);
        $this->assertFalse((bool) $page->fresh()->is_published);
    }

    public function test_low_risk_faq_draft_does_not_publish_or_answer(): void
    {
        $question = FaqQuestion::query()->create([
            'title' => 'Question title',
            'question' => 'Question body?',
            'status' => 'new',
            'is_published' => false,
        ]);

        $result = app(FounderLowRiskDomainActionService::class)->execute('content', 'draft_faq_answer', [
            'entity_id' => $question->id,
            'answer' => 'Proposed answer',
            'category' => 'General',
            'reason_code' => 'faq-draft-' . uniqid(),
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('drafted', $result['status']);
        $this->assertSame('Proposed answer', $result['proposal']['answer']);
        $this->assertNull($question->fresh()->answer);
        $this->assertFalse((bool) $question->fresh()->is_published);
    }

    public function test_faq_cannot_be_published_without_answer(): void
    {
        $question = FaqQuestion::query()->create([
            'title' => 'Unanswered',
            'question' => 'Still open?',
            'status' => 'new',
            'is_published' => false,
        ]);

        $this->expectException(RuntimeException::class);
        app(ContentManagementService::class)->publish('faq_question', $question->id);
    }

    public function test_connectivity_registry_names_real_content_adapters(): void
    {
        $proposals = (array) config('najm-hoda-founder-connectivity.proposal_adapters', []);
        $approvals = (array) config('najm-hoda-founder-connectivity.approval_adapters', []);

        $this->assertSame(ContentManagementService::class, $proposals['content.draft_faq_answer'] ?? null);
        $this->assertSame(ContentManagementService::class, $proposals['content.draft_page_update'] ?? null);
        $this->assertSame(FounderContentLifecycleDecisionService::class, $approvals['content.publish_content'] ?? null);
        $this->assertSame(FounderContentLifecycleDecisionService::class, $approvals['content.delete_content'] ?? null);
    }
}
