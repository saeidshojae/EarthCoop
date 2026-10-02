<?php

namespace Tests\Feature\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Jobs\Communication\DeliverCommunicationRecipient;
use App\Models\CommunicationSenderIdentity;
use App\Models\CommunicationTemplate;
use App\Models\User;
use App\Services\Communication\CommunicationDispatcher;
use App\Services\Communication\CommunicationTemplateService;
use App\Services\Communication\DeliveryFailureClassifier;
use App\Services\Communication\EmailDeliveryAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Tests\TestCase;

class PermanentFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_permanent_delivery_failure_is_recorded_without_retrying(): void
    {
        Queue::fake();
        $recipient = $this->queuedRecipient();

        $adapter = $this->mock(EmailDeliveryAdapter::class);
        $adapter->shouldReceive('send')->once()->andThrow(new InvalidArgumentException('Invalid destination mailbox'));

        $job = new DeliverCommunicationRecipient($recipient->id);
        $job->handle($adapter, app(DeliveryFailureClassifier::class));

        $recipient->refresh();
        $this->assertSame('failed', $recipient->status->value);
        $this->assertNotNull($recipient->failed_at);
        $this->assertDatabaseHas('communication_delivery_attempts', [
            'communication_recipient_id' => $recipient->id,
            'attempt_number' => 1,
            'status' => 'failed',
            'failure_class' => 'permanent',
        ]);
    }

    public function test_successful_delivery_appends_attempt_and_marks_recipient_sent(): void
    {
        Queue::fake();
        $recipient = $this->queuedRecipient();

        $adapter = $this->mock(EmailDeliveryAdapter::class);
        $adapter->shouldReceive('send')->once()->andReturn([
            'provider' => 'laravel-mail',
            'provider_message_id' => 'message-123',
        ]);

        $job = new DeliverCommunicationRecipient($recipient->id);
        $job->handle($adapter, app(DeliveryFailureClassifier::class));

        $recipient->refresh();
        $this->assertSame('sent', $recipient->status->value);
        $this->assertNotNull($recipient->sent_at);
        $this->assertDatabaseHas('communication_delivery_attempts', [
            'communication_recipient_id' => $recipient->id,
            'attempt_number' => 1,
            'provider' => 'laravel-mail',
            'provider_message_id' => 'message-123',
            'status' => 'sent',
        ]);
    }

    private function queuedRecipient(): \App\Models\CommunicationRecipient
    {
        $sender = CommunicationSenderIdentity::query()->create([
            'key' => 'system',
            'email' => 'system@earthcoop.ir',
            'display_name' => 'EarthCoop',
            'is_active' => true,
            'is_default' => true,
        ]);
        $template = CommunicationTemplate::query()->create([
            'key' => 'delivery.permanent-test',
            'name' => 'Permanent failure test',
            'classification' => CommunicationClassification::Operational,
            'is_active' => true,
        ]);
        app(CommunicationTemplateService::class)->publish(
            $template,
            'fa',
            'سلام {{first_name}}',
            '<p>سلام {{first_name}}</p>',
            ['first_name' => ['required' => true]],
            $sender,
        );
        $user = User::factory()->create();

        $communication = app(CommunicationDispatcher::class)->dispatch(
            'delivery.permanent-test',
            ['type' => 'test', 'id' => 'permanent-1'],
            [$user],
            ['first_name' => $user->first_name],
            ['deduplication_key' => 'delivery.permanent-test:user_'.$user->id],
        );

        return $communication->recipients()->firstOrFail()->fresh();
    }
}
