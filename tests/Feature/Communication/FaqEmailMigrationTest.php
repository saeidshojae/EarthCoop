<?php

namespace Tests\Feature\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Models\Communication;
use App\Models\CommunicationSenderIdentity;
use App\Models\CommunicationTemplate;
use App\Models\FaqQuestion;
use App\Models\User;
use App\Services\Communication\CommunicationTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class FaqEmailMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_answered_faq_enters_canonical_communication_once_and_marks_notification_accepted(): void
    {
        Queue::fake();
        Mail::fake();
        $this->canonicalFaqTemplate();

        $admin = User::factory()->create(['is_admin' => true]);
        $question = FaqQuestion::query()->create([
            'contact_name' => 'Guest',
            'contact_email' => 'faq-guest@example.test',
            'title' => 'FAQ title',
            'question' => 'Question body',
            'status' => 'new',
            'is_published' => false,
        ]);

        $payload = [
            'title' => $question->title,
            'question' => $question->question,
            'answer' => 'Canonical answer',
            'status' => 'answered',
            'category' => null,
        ];

        $this->actingAs($admin)
            ->put('/admin/faq-questions/'.$question->id, $payload)
            ->assertRedirect();

        $question->refresh();
        $this->assertNotNull($question->notified_at);
        $this->assertDatabaseHas('communications', [
            'source_type' => 'faq',
            'source_id' => (string) $question->id,
            'deduplication_key' => 'faq:'.$question->id.':answered',
        ]);
        $this->assertDatabaseHas('communication_recipients', [
            'email' => 'faq-guest@example.test',
            'user_id' => null,
        ]);
        $this->assertSame(1, Communication::query()->where('deduplication_key', 'faq:'.$question->id.':answered')->count());

        $this->actingAs($admin)
            ->put('/admin/faq-questions/'.$question->id, $payload)
            ->assertRedirect();

        $this->assertSame(1, Communication::query()->where('deduplication_key', 'faq:'.$question->id.':answered')->count());
        Mail::assertNothingSent();
    }

    private function canonicalFaqTemplate(): void
    {
        $sender = CommunicationSenderIdentity::query()->create([
            'key' => 'faq',
            'email' => 'faq@earthcoop.ir',
            'display_name' => 'EarthCoop FAQ',
            'reply_to' => 'support@earthcoop.ir',
            'is_active' => true,
            'is_default' => false,
        ]);

        $template = CommunicationTemplate::query()->create([
            'key' => 'faq.answer',
            'name' => 'پاسخ پرسش متداول',
            'category' => 'faq',
            'classification' => CommunicationClassification::Operational,
            'is_active' => true,
        ]);

        app(CommunicationTemplateService::class)->publish(
            $template,
            'fa',
            'پاسخ به پرسش: {{title}}',
            '<p>{{answer}}</p>',
            [
                'title' => ['type' => 'string', 'required' => true],
                'answer' => ['type' => 'string', 'required' => true],
            ],
            $sender,
        );
    }
}
