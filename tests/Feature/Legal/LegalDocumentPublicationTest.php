<?php

namespace Tests\Feature\Legal;

use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use App\Models\User;
use App\Services\Legal\LegalDocumentPublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LegalDocumentPublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishing_freezes_content_and_retires_previous_version(): void
    {
        $publisher = User::factory()->create();
        $document = LegalDocument::create([
            'slug' => 'membership',
            'title' => 'اساسنامه',
            'source_type' => 'terms',
        ]);
        $first = LegalDocumentVersion::create([
            'legal_document_id' => $document->id,
            'version_label' => '1.0',
            'language' => 'fa',
            'status' => 'draft',
        ]);
        $service = app(LegalDocumentPublicationService::class);
        $published = $service->publish($first, 'ماده اول', $publisher->id);

        $this->assertSame('published', $published->status);
        $this->assertSame(hash('sha256', 'ماده اول'), $published->content_sha256);
        $this->assertTrue($service->verifyIntegrity($published));
        $this->assertSame($first->id, $service->current('membership')->id);

        $second = LegalDocumentVersion::create([
            'legal_document_id' => $document->id,
            'version_label' => '1.1',
            'language' => 'fa',
            'status' => 'draft',
        ]);
        $service->publish($second, 'ماده دوم', $publisher->id);

        $this->assertSame('retired', $first->fresh()->status);
        $this->assertSame('ماده اول', $first->fresh()->content_snapshot);
        $this->assertSame($second->id, $service->current('membership')->id);
    }

    public function test_published_version_cannot_be_published_again(): void
    {
        $publisher = User::factory()->create();
        $document = LegalDocument::create([
            'slug' => 'najm-bahar',
            'title' => 'نجم بهار',
            'source_type' => 'najm_bahar_agreements',
        ]);
        $version = LegalDocumentVersion::create([
            'legal_document_id' => $document->id,
            'version_label' => '1.0',
            'language' => 'fa',
            'status' => 'draft',
        ]);
        $service = app(LegalDocumentPublicationService::class);
        $service->publish($version, 'متن اصلی', $publisher->id);

        $this->expectException(ValidationException::class);
        $service->publish($version, 'متن متفاوت', $publisher->id);
    }

    public function test_empty_snapshot_is_not_published(): void
    {
        $publisher = User::factory()->create();
        $document = LegalDocument::create([
            'slug' => 'terms',
            'title' => 'شرایط استفاده',
            'source_type' => 'terms',
        ]);
        $version = LegalDocumentVersion::create([
            'legal_document_id' => $document->id,
            'version_label' => '1.0',
            'language' => 'fa',
            'status' => 'draft',
        ]);
        $this->expectException(ValidationException::class);
        app(LegalDocumentPublicationService::class)->publish($version, ' ', $publisher->id);
    }

    public function test_published_snapshot_cannot_be_edited_through_model(): void
    {
        $publisher = User::factory()->create();
        $document = LegalDocument::create([
            'slug' => 'locked-terms',
            'title' => 'شرایط',
            'source_type' => 'terms',
        ]);
        $version = LegalDocumentVersion::create([
            'legal_document_id' => $document->id,
            'version_label' => '1.0',
            'language' => 'fa',
            'status' => 'draft',
        ]);
        app(LegalDocumentPublicationService::class)->publish($version, 'متن ثابت', $publisher->id);

        $this->expectException(\LogicException::class);
        $version->fresh()->update(['content_snapshot' => 'متن تغییر داده شده']);
    }
}
