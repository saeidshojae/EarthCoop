<?php

namespace Tests\Feature\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Enums\Communication\DeliveryStatus;
use App\Jobs\Communication\DeliverCommunicationRecipient;
use App\Models\CommunicationSenderIdentity;
use App\Models\CommunicationTemplate;
use App\Services\Communication\CommunicationDispatcher;
use App\Services\Communication\CommunicationTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class ExternalRecipientDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_email_recipient_enters_canonical_queue_without_fake_user_account(): void
    {
        Queue::fake();
        [$template] = $this->fixture();

        $communication = app(CommunicationDispatcher::class)->dispatchExternal(
            $template->key,
            ['type' => 'faq', 'id' => '42'],
            [['email' => 'guest@example.test', 'locale' => 'fa']],
            ['display_name' => 'مهمان'],
            ['deduplication_key' => 'faq:42:answered'],
        );

        $recipient = $communication->recipients()->firstOrFail();

        $this->assertNull($recipient->user_id);
        $this->assertSame('guest@example.test', $recipient->email);
        $this->assertSame(DeliveryStatus::Queued, $recipient->status);
        $this->assertSame('external_operational', data_get($recipient->preference_decision, 'reason'));
        Queue::assertPushed(DeliverCommunicationRecipient::class, fn ($job): bool => true);
    }

    public function test_invalid_external_email_is_audited_but_not_queued(): void
    {
        Queue::fake();
        [$template] = $this->fixture();

        $communication = app(CommunicationDispatcher::class)->dispatchExternal(
            $template->key,
            ['type' => 'faq', 'id' => '43'],
            [['email' => 'invalid-address', 'locale' => 'fa']],
            ['display_name' => 'مهمان'],
            ['deduplication_key' => 'faq:43:answered'],
        );

        $recipient = $communication->recipients()->firstOrFail();
        $this->assertSame(DeliveryStatus::Invalid, $recipient->status);
        Queue::assertNothingPushed();
    }

    /** @return array{0:CommunicationTemplate} */
    private function fixture(): array
    {
        $sender = CommunicationSenderIdentity::query()->create([
            'key' => 'external-dispatch-'.uniqid(),
            'email' => 'external-'.uniqid().'@earthcoop.ir',
            'display_name' => 'EarthCoop',
            'is_active' => true,
            'is_default' => false,
        ]);

        $template = CommunicationTemplate::query()->create([
            'key' => 'external.test.'.uniqid('', true),
            'name' => 'External recipient test',
            'category' => 'test',
            'classification' => CommunicationClassification::Operational,
            'is_active' => true,
        ]);

        app(CommunicationTemplateService::class)->publish(
            $template,
            'fa',
            'سلام {{display_name}}',
            '<p>{{display_name}}</p>',
            ['display_name' => ['type' => 'string', 'required' => true]],
            $sender,
        );

        return [$template];
    }
}
