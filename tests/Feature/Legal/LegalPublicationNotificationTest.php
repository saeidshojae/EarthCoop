<?php

namespace Tests\Feature\Legal;

use App\Jobs\DeliverLegalPublicationNotice;
use App\Jobs\SendLegalPublicationNotices;
use App\Mail\LegalPublicationNotice;
use App\Notifications\GenericNotification;
use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Services\Legal\LegalDocumentPublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

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
        Queue::assertPushedTimes(DeliverLegalPublicationNotice::class, 2);
    }

    public function test_delivery_job_records_sent_status_for_each_channel(): void
    {
        Mail::fake();
        Notification::fake();

        $user = User::factory()->create(['email' => 'member@example.test', 'is_system' => false]);
        // Legal notices are mandatory transactional communications, even when
        // the member has opted out of general email notifications.
        NotificationSetting::forUser($user->id)->update(['email_notifications' => false]);

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

        foreach (['in_app', 'email'] as $channel) {
            DB::table('legal_publication_deliveries')->insert([
                'legal_document_version_id' => $version->id,
                'user_id' => $user->id,
                'channel' => $channel,
                'status' => 'queued',
                'attempts' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            (new DeliverLegalPublicationNotice($version->id, $user->id, $channel))->handle();
            $this->assertDatabaseHas('legal_publication_deliveries', [
                'legal_document_version_id' => $version->id,
                'user_id' => $user->id,
                'channel' => $channel,
                'status' => 'sent',
            ]);
        }

        Notification::assertSentTo($user, GenericNotification::class);
        Mail::assertSent(LegalPublicationNotice::class);
        Mail::assertSent(LegalPublicationNotice::class, fn ($mail) => $mail->hasTo('member@example.test'));
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
