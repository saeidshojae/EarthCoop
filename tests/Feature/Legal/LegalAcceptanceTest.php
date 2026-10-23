<?php

namespace Tests\Feature\Legal;

use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use App\Models\User;
use App\Services\Legal\LegalAcceptanceService;
use App\Services\Legal\LegalDocumentPublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LegalAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_published_version_can_be_accepted_once(): void
    {
        $user = User::factory()->create();
        $document = LegalDocument::create(['slug' => 'terms', 'title' => 'شرایط', 'source_type' => 'terms']);
        $draft = LegalDocumentVersion::create([
            'legal_document_id' => $document->id, 'version_label' => '1.0', 'language' => 'fa', 'status' => 'draft',
        ]);
        $version = app(LegalDocumentPublicationService::class)->publish($draft, 'متن نهایی', $user->id);
        $service = app(LegalAcceptanceService::class);
        $service->record($user->id, $version, 'membership');
        $service->record($user->id, $version, 'membership');
        $this->assertSame(1, DB::table('legal_document_acceptances')->count());
        $this->assertDatabaseHas('legal_document_acceptances', [
            'user_id' => $user->id, 'legal_document_version_id' => $version->id, 'context' => 'membership',
        ]);
    }

    public function test_minor_revision_inherits_unaccepted_material_requirement_and_preserves_acceptance_history(): void
    {
        $user = User::factory()->create();
        $document = LegalDocument::create(['slug' => 'terms', 'title' => 'شرایط', 'source_type' => 'terms']);
        $publisher = app(LegalDocumentPublicationService::class);

        $material = $publisher->publish(LegalDocumentVersion::create([
            'legal_document_id' => $document->id,
            'version_label' => '1.0',
            'language' => 'fa',
            'status' => 'draft',
            'publication_policy' => 'material',
        ]), 'نسخه اساسی', $user->id);

        app(LegalAcceptanceService::class)->record($user->id, $material, 'membership');

        $minor = $publisher->publish(LegalDocumentVersion::create([
            'legal_document_id' => $document->id,
            'version_label' => '1.1',
            'language' => 'fa',
            'status' => 'draft',
            'publication_policy' => 'minor',
        ]), 'نسخه جزئی', $user->id);

        $this->assertSame($material->id, $material->fresh()->acceptance_required_version_id);
        $this->assertSame($material->id, $minor->acceptance_required_version_id);
        $consent = app(\App\Services\Legal\LegalMembershipConsentService::class);
        $this->assertTrue($consent->canCarryForwardVersion('terms', $material->id));
        $this->assertDatabaseHas('legal_document_acceptances', [
            'user_id' => $user->id,
            'legal_document_version_id' => $material->id,
            'context' => 'membership',
        ]);

        $noticeOnly = $publisher->publish(LegalDocumentVersion::create([
            'legal_document_id' => $document->id,
            'version_label' => '1.2',
            'language' => 'fa',
            'status' => 'draft',
            'publication_policy' => 'notify',
        ]), 'نسخه اطلاع‌رسانی', $user->id);

        $this->assertSame($material->id, $noticeOnly->acceptance_required_version_id);
        $this->assertTrue($consent->canCarryForwardVersion('terms', $noticeOnly->id));

        $newMaterial = $publisher->publish(LegalDocumentVersion::create([
            'legal_document_id' => $document->id,
            'version_label' => '2.0',
            'language' => 'fa',
            'status' => 'draft',
            'publication_policy' => 'material',
        ]), 'نسخه اساسی جدید', $user->id);

        $this->assertSame($newMaterial->id, $newMaterial->acceptance_required_version_id);
        $this->assertNotSame($noticeOnly->acceptance_required_version_id, $newMaterial->acceptance_required_version_id);
        $this->assertFalse($consent->canCarryForwardVersion('terms', $minor->id));
        $this->assertDatabaseHas('legal_document_acceptances', [
            'user_id' => $user->id,
            'legal_document_version_id' => $material->id,
            'context' => 'membership',
        ]);
    }

    public function test_retired_version_cannot_be_newly_accepted(): void
    {
        $user = User::factory()->create();
        $document = LegalDocument::create(['slug' => 'terms', 'title' => 'شرایط', 'source_type' => 'terms']);
        $service = app(LegalDocumentPublicationService::class);
        $old = $service->publish(LegalDocumentVersion::create([
            'legal_document_id' => $document->id, 'version_label' => '1.0', 'status' => 'draft',
        ]), 'نسخه اول', $user->id);
        $service->publish(LegalDocumentVersion::create([
            'legal_document_id' => $document->id, 'version_label' => '1.1', 'status' => 'draft',
        ]), 'نسخه دوم', $user->id);

        $this->expectException(ValidationException::class);
        app(LegalAcceptanceService::class)->record($user->id, $old->fresh(), 'membership');
    }
}
