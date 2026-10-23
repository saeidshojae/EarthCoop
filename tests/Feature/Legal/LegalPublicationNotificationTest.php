<?php

namespace Tests\\Feature\\Legal;

use App\\Jobs\\DeliverLegalPublicationNotice;
use App\\Jobs\\SendLegalPublicationNotices;
use App\\Models\\LegalDocument;
use App\\Models\\LegalDocumentVersion;
use App\\Models\\User;
use App\\Services\\Legal\\LegalDocumentPublicationService;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Queue;
use Tests\\TestCase;

class LegalPublicationNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notify_policy_queues_in_app_and_email_deliveries_with_separate_status_rows(): void
    {
        Queue::fake();
        $user = User::factory()->create(['email' => 'member@example.test', 'is_system' => false]);
        $document = LegalDocument::create(['slug' => 'terms', 'title' => 'شرایط استفاده', 'source_type' => 'terms']);
        $version = app(LegalDocumentPublicationService::class)->publish(
            LegalDocumentVersion::create([
                'legal_document_id' => $document->id,
                'version_label' => '1.1',
                'language' => 'fa',
                'status' => 'draft',
                'publication_policy' => 'notify',
            ]),
            'نسخه جدید',
            $user->id
        );

        (new SendLegalPublicationNotices($version->id))->handle();

        $this->assertDatabaseHas('legal_publication_deliveries', [
            'legal_document_version_id' => $version->id,
            'user_id' => $user->id,
            'channel' => 'in_app',
            'status' => 'queued',
        ]);
        $this->assertDatabaseHas('legal_publication_deliveries', [
            'legal_document_version_id' => $version->id,
            'user_id' => $user->id,
            'channel' => 'email',
            'status' => 'queued',
        ]);
        Queue::assertPushed(DeliverLegalPublicationNotice::class, 2);
    }

    public function test_minor_policy_does_not_queue_publication_notices(): void
    {
        Queue::fake();
        $user = User::factory()->create(['is_system' => false]);
        $document = LegalDocument::create(['slug' => 'terms', 'title' => 'شرایط استفاده', 'source_type' => 'terms']);
        $version = app(LegalDocumentPublicationService::class)->publish(
            LegalDocumentVersion::create([
                'legal_document_id' => $document->id,
                'version_label' => '1.0',
                'language' => 'fa',
                'status' => 'draft',
                'publication_policy' => 'minor',
            ]),
            'نسخه جزئی',
            $user->id
        );

        (new SendLegalPublicationNotices($version->id))->handle();

        $this->assertSame(0, DB::table('legal_publication_deliveries')->count());
        Queue::assertNothingPushed();
    }
}
