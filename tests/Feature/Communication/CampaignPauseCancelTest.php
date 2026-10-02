<?php

namespace Tests\Feature\Communication;

use App\Enums\Communication\CommunicationClassification;
use App\Enums\Communication\CommunicationStatus;
use App\Enums\Communication\DeliveryStatus;
use App\Models\Communication;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationSenderIdentity;
use App\Models\CommunicationTemplate;
use App\Models\User;
use App\Services\Communication\CommunicationCampaignService;
use App\Services\Communication\CommunicationTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CampaignPauseCancelTest extends TestCase
{
    use RefreshDatabase;

    public function test_pause_stops_pending_chunk_without_recalling_already_sent_recipient(): void
    {
        [$campaign, $sentUser, $pendingUser, $versionId] = $this->runningCampaignFixture();
        $service = app(CommunicationCampaignService::class);

        $sent = $this->recordSentRecipient($campaign, $sentUser, $versionId);
        $service->pause($campaign);

        $queued = $service->dispatchChunk($campaign->fresh(), [$pendingUser->id], [
            $pendingUser->id => ['display_name' => 'Pending User'],
        ]);

        $this->assertSame(0, $queued);
        $this->assertSame('paused', $campaign->fresh()->status);
        $this->assertSame(DeliveryStatus::Sent, $sent->fresh()->status);
        $this->assertDatabaseMissing('communication_recipients', ['user_id' => $pendingUser->id]);
    }

    public function test_cancel_stops_pending_chunk_and_marks_only_unsent_campaign_work_cancelled(): void
    {
        [$campaign, $sentUser, $pendingUser, $versionId] = $this->runningCampaignFixture();
        $service = app(CommunicationCampaignService::class);

        $sent = $this->recordSentRecipient($campaign, $sentUser, $versionId);
        $service->cancel($campaign);

        $queued = $service->dispatchChunk($campaign->fresh(), [$pendingUser->id], [
            $pendingUser->id => ['display_name' => 'Pending User'],
        ]);

        $this->assertSame(0, $queued);
        $this->assertSame('cancelled', $campaign->fresh()->status);
        $this->assertNotNull($campaign->fresh()->cancelled_at);
        $this->assertSame(DeliveryStatus::Sent, $sent->fresh()->status);
        $this->assertDatabaseMissing('communication_recipients', ['user_id' => $pendingUser->id]);
    }

    /** @return array{0:CommunicationCampaign,1:User,2:User,3:int} */
    private function runningCampaignFixture(): array
    {
        $sender = CommunicationSenderIdentity::query()->create([
            'key' => 'pause-sender-'.uniqid(),
            'email' => 'pause-'.uniqid().'@earthcoop.ir',
            'display_name' => 'Pause Sender',
            'is_active' => true,
            'is_default' => false,
        ]);

        $template = CommunicationTemplate::query()->create([
            'key' => 'campaign.pause.'.uniqid('', true),
            'name' => 'Campaign pause template',
            'category' => 'test',
            'classification' => CommunicationClassification::Operational,
            'is_active' => true,
        ]);

        $version = app(CommunicationTemplateService::class)->publish(
            $template,
            'fa',
            'سلام {{display_name}}',
            '<p>{{display_name}}</p>',
            ['display_name' => ['type' => 'string', 'required' => true]],
            $sender,
        );

        $sentUser = User::factory()->create(['email' => 'already-sent@example.test']);
        $pendingUser = User::factory()->create(['email' => 'pending-chunk@example.test']);

        $campaign = CommunicationCampaign::query()->create([
            'name' => 'Pause/cancel campaign',
            'status' => 'running',
            'audience_definition' => [
                'key' => 'specific.user',
                'user_ids' => [$sentUser->id, $pendingUser->id],
            ],
            'communication_template_id' => $template->id,
            'communication_sender_identity_id' => $sender->id,
            'classification' => CommunicationClassification::Operational,
            'priority' => 4,
            'confirmed_at' => now(),
        ]);

        return [$campaign, $sentUser, $pendingUser, $version->id];
    }

    private function recordSentRecipient(CommunicationCampaign $campaign, User $user, int $versionId)
    {
        $communication = Communication::query()->create([
            'source_type' => 'campaign',
            'source_id' => (string) $campaign->id,
            'communication_campaign_id' => $campaign->id,
            'communication_template_version_id' => $versionId,
            'classification' => CommunicationClassification::Operational,
            'priority' => 4,
            'context_snapshot' => ['display_name' => 'Already Sent'],
            'status' => CommunicationStatus::Sent,
            'deduplication_key' => 'campaign:'.$campaign->id.':user:'.$user->id,
        ]);

        return $communication->recipients()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'locale' => 'fa',
            'communication_template_version_id' => $versionId,
            'preference_decision' => ['allowed' => true, 'reason' => 'operational_default_on'],
            'status' => DeliveryStatus::Sent,
            'sent_at' => now(),
        ]);
    }
}
