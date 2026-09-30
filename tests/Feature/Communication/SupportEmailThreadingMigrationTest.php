<?php

namespace Tests\Feature\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Enums\Communication\CommunicationStatus;
use App\Enums\Communication\DeliveryStatus;
use App\Models\Communication;
use App\Models\CommunicationSenderIdentity;
use App\Models\CommunicationTemplate;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\Communication\CommunicationTemplateService;
use App\Services\Communication\EmailDeliveryAdapter;
use App\Services\EmailTicketIntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

final class SupportEmailThreadingMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_reply_is_queued_canonically_and_preserves_thread_metadata_on_comment(): void
    {
        Queue::fake();
        Mail::fake();
        config()->set('app.url', 'https://earthcoop.ir');
        config()->set('system-identities.support.email', 'support@earthcoop.ir');
        config()->set('system-identities.support.reply_to', 'support@earthcoop.ir');
        config()->set('system-identities.support.mail_from_name', 'تیم پشتیبانی EarthCoop');
        $this->supportTemplate();

        $ticket = Ticket::query()->create([
            'tracking_code' => 'TK-THREAD1',
            'subject' => 'Threading test',
            'message' => 'Initial request',
            'status' => 'open',
            'priority' => 'normal',
            'name' => 'Guest User',
            'email' => 'ticket-guest@example.test',
        ]);
        $commenter = User::factory()->create();
        $comment = TicketComment::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $commenter->id,
            'message' => 'Support reply',
        ]);

        $ok = app(EmailTicketIntegrationService::class)->sendTicketReplyToEmail($ticket, $comment);

        $this->assertTrue($ok);
        $communication = Communication::query()
            ->where('source_type', 'support.ticket_reply')
            ->where('source_id', (string) $comment->id)
            ->firstOrFail();
        $this->assertSame('support.ticket_reply:'.$comment->id, $communication->deduplication_key);
        $this->assertDatabaseHas('communication_recipients', [
            'communication_id' => $communication->id,
            'user_id' => null,
            'email' => 'ticket-guest@example.test',
        ]);

        $metadata = (array) ($comment->fresh()->metadata ?? []);
        $this->assertTrue((bool) ($metadata['email_sent'] ?? false));
        $this->assertSame('support', $metadata['sender_identity'] ?? null);
        $this->assertSame('support@earthcoop.ir', $metadata['sender_address'] ?? null);
        $this->assertNotEmpty($metadata['message_id'] ?? null);

        $headers = (array) data_get($communication->context_snapshot, '_delivery_headers', []);
        $this->assertSame($metadata['message_id'], $headers['Message-ID'] ?? null);
        $this->assertSame('<ticket-'.$ticket->id.'@earthcoop.ir>', $headers['In-Reply-To'] ?? null);
        $this->assertSame('<ticket-'.$ticket->id.'@earthcoop.ir>', $headers['References'] ?? null);
    }

    public function test_email_adapter_applies_support_sender_reply_to_and_thread_headers(): void
    {
        [$sender, $template] = $this->supportTemplate();
        $version = $template->versions()->where('locale', 'fa')->latest('version')->firstOrFail();
        $messageId = '<ticket-55-comment-99@earthcoop.ir>';
        $threadId = '<ticket-55@earthcoop.ir>';

        $communication = Communication::query()->create([
            'source_type' => 'support.ticket_reply',
            'source_id' => '99',
            'communication_template_version_id' => $version->id,
            'classification' => CommunicationClassification::Operational,
            'priority' => 1,
            'context_snapshot' => [
                'subject' => 'TK-55 - Help',
                'rendered_html' => '<p>Reply</p>',
                '_delivery_headers' => [
                    'Message-ID' => $messageId,
                    'In-Reply-To' => $threadId,
                    'References' => $threadId,
                ],
            ],
            'status' => CommunicationStatus::Queued,
            'deduplication_key' => 'support.ticket_reply:99',
        ]);

        $recipient = $communication->recipients()->create([
            'user_id' => null,
            'email' => 'recipient@example.test',
            'locale' => 'fa',
            'communication_template_version_id' => $version->id,
            'preference_decision' => ['allowed' => true, 'reason' => 'external_operational'],
            'status' => DeliveryStatus::Queued,
            'queued_at' => now(),
        ]);

        $captured = [];
        Mail::shouldReceive('html')->once()->andReturnUsing(function ($body, $callback) use (&$captured): void {
            $email = new Email();
            $message = new Message($email);
            $callback($message);

            $captured = [
                'from' => $email->getFrom()[0]->getAddress(),
                'reply_to' => $email->getReplyTo()[0]->getAddress(),
                'raw' => $email->toString(),
            ];
        });

        app(EmailDeliveryAdapter::class)->send($recipient);

        $this->assertSame($sender->email, $captured['from']);
        $this->assertSame($sender->reply_to, $captured['reply_to']);
        $this->assertStringContainsString($messageId, $captured['raw']);
        $this->assertStringContainsString($threadId, $captured['raw']);
        $this->assertStringContainsString('In-Reply-To:', $captured['raw']);
        $this->assertStringContainsString('References:', $captured['raw']);
    }

    /** @return array{0:CommunicationSenderIdentity,1:CommunicationTemplate} */
    private function supportTemplate(): array
    {
        $sender = CommunicationSenderIdentity::query()->firstOrCreate(
            ['key' => 'support'],
            [
                'email' => 'support@earthcoop.ir',
                'display_name' => 'تیم پشتیبانی EarthCoop',
                'reply_to' => 'support@earthcoop.ir',
                'system_identity_key' => 'support',
                'is_active' => true,
                'is_default' => false,
            ],
        );

        $template = CommunicationTemplate::query()->firstOrCreate(
            ['key' => 'support.ticket_reply'],
            [
                'name' => 'پاسخ تیکت پشتیبانی',
                'category' => 'support',
                'classification' => CommunicationClassification::Operational,
                'is_active' => true,
            ],
        );

        if (! $template->versions()->where('locale', 'fa')->exists()) {
            app(CommunicationTemplateService::class)->publish(
                $template,
                'fa',
                '{{subject}}',
                '{{rendered_html}}',
                [
                    'subject' => ['type' => 'string', 'required' => true],
                    'rendered_html' => ['type' => 'string', 'required' => true],
                    '_delivery_headers' => ['type' => 'array', 'required' => false],
                ],
                $sender,
            );
        }

        return [$sender, $template->fresh()];
    }
}
