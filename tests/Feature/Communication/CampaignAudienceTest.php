<?php

namespace Tests\Feature\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationPreference;
use App\Models\CommunicationSenderIdentity;
use App\Models\CommunicationTemplate;
use App\Models\User;
use App\Services\Communication\CommunicationCampaignService;
use App\Services\Communication\CommunicationTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class CampaignAudienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_counts_matched_eligible_suppressed_and_invalid_recipients(): void
    {
        [$campaign, $users] = $this->campaignWithUsers();

        CommunicationPreference::query()->create([
            'user_id' => $users[1]->id,
            'topic_key' => $campaign->template->key,
            'channel' => 'email',
            'preference' => 'off',
        ]);

        $users[2]->forceFill(['email' => 'not-an-email'])->save();

        $preview = app(CommunicationCampaignService::class)->preview($campaign);

        $this->assertSame(3, $preview['matched']);
        $this->assertSame(1, $preview['eligible']);
        $this->assertSame(1, $preview['suppressed']);
        $this->assertSame(1, $preview['invalid']);
    }

    public function test_sample_render_uses_published_template_and_recipient_context(): void
    {
        [$campaign, $users] = $this->campaignWithUsers();

        $sample = app(CommunicationCampaignService::class)->sampleRender($campaign, $users[0], [
            'display_name' => 'سعید',
        ]);

        $this->assertSame('سلام سعید', $sample['subject']);
        $this->assertStringContainsString('سعید', $sample['body']);
    }

    public function test_preview_rejects_unregistered_non_executable_audience_definition(): void
    {
        [$campaign] = $this->campaignWithUsers();
        $campaign->update(['audience_definition' => ['key' => 'raw.sql.audience', 'query' => 'select * from users']]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unregistered communication audience');

        app(CommunicationCampaignService::class)->preview($campaign->fresh());
    }

    public function test_large_campaign_requires_explicit_elevated_confirmation_at_configured_threshold(): void
    {
        config()->set('communications.campaigns.elevated_confirmation_threshold', 2);
        [$campaign] = $this->campaignWithUsers();
        $actor = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('elevated_confirmation_required');

        app(CommunicationCampaignService::class)->confirm($campaign, $actor, false);
    }

    public function test_large_campaign_can_be_confirmed_after_explicit_elevated_confirmation(): void
    {
        config()->set('communications.campaigns.elevated_confirmation_threshold', 2);
        [$campaign] = $this->campaignWithUsers();
        $actor = User::factory()->create();

        $confirmed = app(CommunicationCampaignService::class)->confirm($campaign, $actor, true);

        $this->assertNotNull($confirmed->confirmed_at);
        $this->assertSame($actor->id, $confirmed->approved_by);
        $this->assertContains($confirmed->status, ['confirmed', 'scheduled', 'running']);
    }

    public function test_future_campaign_stays_scheduled_until_due_then_can_start(): void
    {
        config()->set('communications.campaigns.elevated_confirmation_threshold', 100);
        [$campaign] = $this->campaignWithUsers();
        $actor = User::factory()->create();
        $campaign->update(['scheduled_at' => now()->addHour()]);

        $service = app(CommunicationCampaignService::class);
        $confirmed = $service->confirm($campaign->fresh(), $actor, false);

        $this->assertSame('scheduled', $confirmed->status);
        $this->assertFalse($service->startIfDue($confirmed->fresh()));

        $confirmed->update(['scheduled_at' => now()->subMinute()]);
        $this->assertTrue($service->startIfDue($confirmed->fresh()));
        $this->assertSame('running', $confirmed->fresh()->status);
    }

    /** @return array{0:CommunicationCampaign,1:array<int,User>} */
    private function campaignWithUsers(): array
    {
        $sender = CommunicationSenderIdentity::query()->create([
            'key' => 'campaign-sender-'.uniqid(),
            'email' => 'campaign-'.uniqid().'@earthcoop.ir',
            'display_name' => 'Campaign Sender',
            'is_active' => true,
            'is_default' => false,
        ]);

        $template = CommunicationTemplate::query()->create([
            'key' => 'campaign.test.'.uniqid('', true),
            'name' => 'Campaign test template',
            'category' => 'test',
            'classification' => CommunicationClassification::Operational,
            'is_active' => true,
        ]);

        app(CommunicationTemplateService::class)->publish(
            $template,
            'fa',
            'سلام {{display_name}}',
            '<p>سلام {{display_name}}</p>',
            ['display_name' => ['type' => 'string', 'required' => true]],
            $sender,
        );

        $users = [
            User::factory()->create(['email' => 'campaign-a@example.test']),
            User::factory()->create(['email' => 'campaign-b@example.test']),
            User::factory()->create(['email' => 'campaign-c@example.test']),
        ];

        $campaign = CommunicationCampaign::query()->create([
            'name' => 'Task 10 campaign',
            'status' => 'draft',
            'audience_definition' => [
                'key' => 'specific.user',
                'user_ids' => array_map(fn (User $user): int => $user->id, $users),
            ],
            'communication_template_id' => $template->id,
            'communication_sender_identity_id' => $sender->id,
            'classification' => CommunicationClassification::Operational,
            'priority' => 4,
        ]);

        return [$campaign, $users];
    }
}
