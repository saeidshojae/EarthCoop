<?php

namespace Tests\Feature\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Models\Communication;
use App\Models\CommunicationSenderIdentity;
use App\Models\CommunicationTemplate;
use App\Models\Ticket;
use App\Services\Communication\CommunicationTemplateService;
use App\Services\EmailTicketIntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class TicketCreatedEmailMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_created_email_is_queued_once_through_canonical_engine(): void
    {
        Queue::fake();
        Mail::fake();
        $this->canonicalTemplate();

        $ticket = Ticket::query()->create([
            'tracking_code' => 'TK-CREATED1',
            'subject' => 'Created migration',
            'message' => 'Initial message',
            'status' => 'open',
            'priority' => 'normal',
            'name' => 'Guest User',
            'email' => 'created-guest@example.test',
        ]);

        $service = app(EmailTicketIntegrationService::class);
        $this->assertTrue($service->sendTicketCreatedEmail($ticket));
        $this->assertTrue($service->sendTicketCreatedEmail($ticket));

        $this->assertSame(1, Communication::query()
            ->where('deduplication_key', 'support.ticket_created:'.$ticket->id)
            ->count());
        $this->assertDatabaseHas('communications', [
            'source_type' => 'support.ticket_created',
            'source_id' => (string) $ticket->id,
            'deduplication_key' => 'support.ticket_created:'.$ticket->id,
        ]);
        $this->assertDatabaseHas('communication_recipients', [
            'email' => 'created-guest@example.test',
            'user_id' => null,
        ]);
        Mail::assertNothingSent();
    }

    private function canonicalTemplate(): void
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
            ['key' => 'support.ticket_created'],
            [
                'name' => 'ایجاد تیکت پشتیبانی',
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
                ],
                $sender,
            );
        }
    }
}
