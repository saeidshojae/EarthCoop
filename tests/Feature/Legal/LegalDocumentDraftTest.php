<?php

namespace Tests\Feature\Legal;

use App\Models\LegalDocument;
use App\Models\Term;
use App\Services\Legal\LegalDocumentDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LegalDocumentDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_is_registered_against_existing_admin_parent_without_mutating_terms(): void
    {
        $root = Term::create(['title' => 'عضویت', 'message' => 'قواعد اولیه']);
        $version = app(LegalDocumentDraftService::class)
            ->createFromAdminSource('membership', 'عضویت', 'terms', $root->id, '1.0');

        $this->assertSame('draft', $version->status);
        $this->assertNull($version->published_at);
        $this->assertNull($version->content_snapshot);
        $this->assertSame($root->id, LegalDocument::firstOrFail()->source_root_id);
        $this->assertSame('قواعد اولیه', $root->fresh()->message);
    }

    public function test_existing_version_cannot_be_recreated(): void
    {
        $root = Term::create(['title' => 'عضویت', 'message' => 'متن']);
        $drafts = app(LegalDocumentDraftService::class);
        $drafts->createFromAdminSource('membership', 'عضویت', 'terms', $root->id, '1.0');

        $this->expectException(ValidationException::class);
        $drafts->createFromAdminSource('membership', 'عضویت', 'terms', $root->id, '1.0');
    }

    public function test_missing_admin_root_does_not_create_document(): void
    {
        try {
            app(LegalDocumentDraftService::class)
                ->createFromAdminSource('membership', 'عضویت', 'terms', 999999, '1.0');
            $this->fail('Expected missing admin source');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            $this->assertSame(0, LegalDocument::count());
        }
    }
}
