<?php

namespace Tests\Feature\Legal;

use App\Models\NajmBaharAgreement;
use App\Models\Term;
use App\Models\User;
use App\Services\Legal\LegalMarkdownImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LegalMarkdownImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_only_reads_and_import_requires_explicit_separate_action(): void
    {
        $service = app(LegalMarkdownImportService::class);
        $source = $service->preview('membership');
        $this->assertSame('terms', $source['source_type']);
        $this->assertNotEmpty($source['children']);
        $this->assertSame(0, Term::count());

        $rootId = $service->importToNewRoot('membership');
        $this->assertDatabaseHas('terms', ['id' => $rootId, 'parent_id' => null]);
        $this->assertSame(count($source['children']), Term::where('parent_id', $rootId)->count());
    }

    public function test_import_rejects_unreviewed_content_fingerprint_without_creating_rows(): void
    {
        $this->withoutMiddleware(\\App\\Http\\Middleware\\AdminMiddleware::class);
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.legal-versions.import', 'najm-bahar'), [
            'confirm_import' => '1',
            'preview_sha256' => str_repeat('0', 64),
        ])->assertSessionHasErrors('document');

        $this->assertSame(0, NajmBaharAgreement::count());
        $this->assertSame(0, \\App\\Models\\LegalDocument::count());
    }

    public function test_import_does_not_overwrite_existing_root_with_same_title(): void
    {
        $service = app(LegalMarkdownImportService::class);
        $source = $service->preview('terms');
        Term::create(['title' => $source['title'], 'message' => 'قدیمی']);

        $this->expectException(ValidationException::class);
        $service->importToNewRoot('terms');
    }

    public function test_najm_import_creates_ordered_children_without_publishing(): void
    {
        $service = app(LegalMarkdownImportService::class);
        $source = $service->preview('najm-bahar');
        $rootId = $service->importToNewRoot('najm-bahar');
        $rows = NajmBaharAgreement::where('parent_id', $rootId)->orderBy('order')->get();
        $this->assertCount(count($source['children']), $rows);
        $this->assertSame(1, $rows->first()->order);
    }
}
