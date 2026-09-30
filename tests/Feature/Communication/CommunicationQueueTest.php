<?php

namespace Tests\Feature\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Jobs\Communication\DeliverCommunicationRecipient;
use App\Models\CommunicationSenderIdentity;
use App\Models\CommunicationTemplate;
use App\Models\User;
use App\Services\Communication\CommunicationDispatcher;
use App\Services\Communication\CommunicationTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CommunicationQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_queues_required_mail_on_critical_queue_without_sending_inline(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $this->seedTemplate('security.required', CommunicationClassification::Required);

        $communication = app(CommunicationDispatcher::class)->dispatch(
            'security.required',
            ['type' => 'security-test', 'id' => 'required-1'],
            [$user],
            ['first_name' => $user->first_name],
            ['deduplication_key' => 'security.required:user_'.$user->id.':1'],
        );

        $recipient = $communication->recipients()->firstOrFail();
        $this->assertSame('queued', $recipient->fresh()->status->value);
        $this->assertNotNull($recipient->fresh()->queued_at);

        Queue::assertPushedOn('communications-critical', DeliverCommunicationRecipient::class);
        Queue::assertPushed(DeliverCommunicationRecipient::class, 1);
    }

    public function test_dispatch_queues_normal_operational_mail_on_normal_queue(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $this->seedTemplate('reports.member.weekly', CommunicationClassification::Operational);

        app(CommunicationDispatcher::class)->dispatch(
            'reports.member.weekly',
            ['type' => 'report', 'id' => 'week-1'],
            [$user],
            ['first_name' => $user->first_name],
            ['deduplication_key' => 'reports.member.weekly:user_'.$user->id.':week-1'],
        );

        Queue::assertPushedOn('communications-normal', DeliverCommunicationRecipient::class);
    }

    public function test_explicit_bulk_dispatch_uses_bulk_queue_without_overriding_required_priority(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $this->seedTemplate('campaign.news', CommunicationClassification::Optional);
        \App\Models\CommunicationPreference::query()->create([
            'user_id' => $user->id,
            'topic_key' => 'campaign.news',
            'channel' => 'email',
            'preference' => 'on',
        ]);

        app(CommunicationDispatcher::class)->dispatch(
            'campaign.news',
            ['type' => 'campaign', 'id' => 'campaign-1'],
            [$user],
            ['first_name' => $user->first_name],
            [
                'deduplication_key' => 'campaign.news:user_'.$user->id.':campaign-1',
                'delivery_class' => 'bulk',
            ],
        );

        Queue::assertPushedOn('communications-bulk', DeliverCommunicationRecipient::class);

        Queue::fake();
        $this->seedTemplate('security.bulk-flagged', CommunicationClassification::Required);
        app(CommunicationDispatcher::class)->dispatch(
            'security.bulk-flagged',
            ['type' => 'security-test', 'id' => 'required-2'],
            [$user],
            ['first_name' => $user->first_name],
            [
                'deduplication_key' => 'security.bulk-flagged:user_'.$user->id.':2',
                'delivery_class' => 'bulk',
            ],
        );

        Queue::assertPushedOn('communications-critical', DeliverCommunicationRecipient::class);
    }

    private function seedTemplate(string $key, CommunicationClassification $classification): void
    {
        $sender = CommunicationSenderIdentity::query()->firstOrCreate(
            ['key' => 'system'],
            [
                'email' => 'system@earthcoop.ir',
                'display_name' => 'EarthCoop',
                'reply_to' => 'support@earthcoop.ir',
                'is_active' => true,
                'is_default' => true,
            ],
        );
        $template = CommunicationTemplate::query()->create([
            'key' => $key,
            'name' => $key,
            'classification' => $classification,
            'is_active' => true,
        ]);
        app(CommunicationTemplateService::class)->publish(
            $template,
            'fa',
            'سلام {{first_name}}',
            '<p>پیام برای {{first_name}}</p>',
            ['first_name' => ['required' => true]],
            $sender,
        );
    }
}
