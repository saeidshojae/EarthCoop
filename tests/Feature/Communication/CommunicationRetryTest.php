<?php

namespace Tests\Feature\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Jobs\Communication\DeliverCommunicationRecipient;
use App\Models\CommunicationSenderIdentity;
use App\Models\CommunicationTemplate;
use App\Models\User;
use App\Services\Communication\CommunicationDispatcher;
use App\Services\Communication\CommunicationTemplateService;
use App\Services\Communication\EmailDeliveryAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class CommunicationRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_transient_delivery_failure_is_recorded_and_rethrown_for_bounded_queue_retry(): void
    {
        Queue::fake();
        $recipient = $this->queuedRecipient();

        $adapter = $this->mock(EmailDeliveryAdapter::class);
        $adapter->shouldReceive('send')->once()->andThrow(new RuntimeException('Connection timed out'));

        $job = new DeliverCommunicationRecipient($recipient->id);
        $this->assertSame(4, $job->tries);
        $this->assertSame([60, 300, 900], $job->backoff());

        try {
            $job->handle($adapter, app(\App\Services\Communication\DeliveryFailureClassifier::class));
            $this->fail('Transient failure must be rethrown so Laravel queue can retry it.');
        } catch (RuntimeException $e) {
            $this->assertSame('Connection timed out', $e->getMessage());
        }

        $recipient->refresh();
        $this->assertSame('retrying', $recipient->status->value);
        $this->assertNull($recipient->failed_at);
        $this->assertDatabaseHas('communication_delivery_attempts', [
            'communication_recipient_id' => $recipient->id,
            'attempt_number' => 1,
            'status' => 'failed',
            'failure_class' => 'transient',
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
            'key' => 'delivery.retry-test',
            'name' => 'Retry test',
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
            'delivery.retry-test',
            ['type' => 'test', 'id' => 'retry-1'],
            [$user],
            ['first_name' => $user->first_name],
            ['deduplication_key' => 'delivery.retry-test:user_'.$user->id],
        );

        return $communication->recipients()->firstOrFail()->fresh();
    }
}
