<?php

namespace Tests\Feature\Legal;

use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use App\Models\Term;
use App\Models\User;
use App\Services\Legal\LegalDocumentSourceSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPublicationPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_changed_editor_content_cannot_be_published_from_an_old_preview(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\AdminMiddleware::class);
        $admin = User::factory()->create();
        $root = Term::create(['title' => 'قرارداد آزمایشی', 'message' => 'متن نخست']);
        $document = LegalDocument::create([
            'slug' => 'preview-check',
            'title' => 'قرارداد آزمایشی',
            'source_type' => 'terms',
            'source_root_id' => $root->id,
        ]);
        $version = LegalDocumentVersion::create([
            'legal_document_id' => $document->id,
            'version_label' => '1.0',
            'language' => 'fa',
            'status' => 'draft',
        ]);

        $reviewed = app(LegalDocumentSourceSnapshotService::class)->capture('terms', $root->id);
        $root->update(['message' => 'متن تازه و بررسی‌نشده']);

        $this->actingAs($admin)->post(route('admin.legal-versions.publish', $version), [
            'confirm_publish' => '1',
            'preview_sha256' => hash('sha256', $reviewed),
        ])->assertSessionHasErrors('content');

        $this->assertSame('draft', $version->fresh()->status);
        $this->assertNull($version->fresh()->content_snapshot);
    }

    public function test_matching_reviewed_snapshot_can_be_published(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\AdminMiddleware::class);
        $admin = User::factory()->create();
        $root = Term::create(['title' => 'قرارداد آزمایشی', 'message' => 'متن قطعی']);
        $document = LegalDocument::create([
            'slug' => 'preview-publish',
            'title' => 'قرارداد آزمایشی',
            'source_type' => 'terms',
            'source_root_id' => $root->id,
        ]);
        $version = LegalDocumentVersion::create([
            'legal_document_id' => $document->id,
            'version_label' => '1.0',
            'language' => 'fa',
            'status' => 'draft',
        ]);
        $reviewed = app(LegalDocumentSourceSnapshotService::class)->capture('terms', $root->id);

        $this->actingAs($admin)->post(route('admin.legal-versions.publish', $version), [
            'confirm_publish' => '1',
            'preview_sha256' => hash('sha256', $reviewed),
        ])->assertRedirect(route('admin.legal-versions.index'));

        $this->assertSame('published', $version->fresh()->status);
        $this->assertSame($reviewed, $version->fresh()->content_snapshot);
    }
}
