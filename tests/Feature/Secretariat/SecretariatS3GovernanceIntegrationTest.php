<?php

namespace Tests\Feature\Secretariat;

use App\Models\Group;
use App\Models\GroupSession;
use App\Models\NajmHodaGroupMeetingMinute;
use App\Models\User;
use App\Modules\Governance\Models\Proposal;
use App\Modules\Governance\Models\Resolution;
use App\Modules\Secretariat\Services\SecretariatGovernanceIntegrationService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SecretariatS3GovernanceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_approved_minute_can_create_idempotent_registry_draft_without_mutating_source(): void
    {
        [$actor, $group] = $this->groupContext();
        $session = GroupSession::query()->create([
            'group_id' => $group->id,
            'created_by' => $actor->id,
            'title' => 'جلسه آب و کشاورزی',
            'subject' => 'بررسی تخصیص آب',
            'agenda' => 'پیشنهادها و تصمیمات',
            'status' => 'ended',
            'starts_at' => now()->subHours(2),
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subHour(),
        ]);
        $minute = NajmHodaGroupMeetingMinute::query()->create([
            'group_session_id' => $session->id,
            'group_id' => $group->id,
            'status' => 'approved',
            'summary' => 'خلاصه رسمی جلسه',
            'minutes' => 'متن کامل صورتجلسه مصوب',
            'generated_by' => $actor->id,
            'approved_by' => $actor->id,
            'generated_at' => now()->subMinutes(30),
            'approved_at' => now()->subMinutes(20),
        ]);

        $sourceBefore = $minute->fresh()->getAttributes();
        $service = app(SecretariatGovernanceIntegrationService::class);

        $first = $service->proposeApprovedMeetingMinute($minute, $actor);
        $second = $service->proposeApprovedMeetingMinute($minute, $actor);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('draft', $first->status);
        $this->assertSame('meeting_minute', $first->record_type);
        $this->assertSame('meeting_minute', $first->source_type);
        $this->assertSame($minute->id, $first->source_id);
        $this->assertSame('متن کامل صورتجلسه مصوب', $first->currentVersion->body);
        $this->assertNull($first->registry_number);
        $this->assertSame(1, $first->office->records()->where('source_type', 'meeting_minute')->where('source_id', $minute->id)->count());
        $this->assertSame($sourceBefore, $minute->fresh()->getAttributes());
    }

    public function test_draft_minute_is_rejected_by_registry_adapter(): void
    {
        [$actor, $group] = $this->groupContext();
        $session = GroupSession::query()->create([
            'group_id' => $group->id,
            'created_by' => $actor->id,
            'title' => 'جلسه ناتمام',
            'status' => 'ended',
            'starts_at' => now()->subHour(),
            'ended_at' => now(),
        ]);
        $minute = NajmHodaGroupMeetingMinute::query()->create([
            'group_session_id' => $session->id,
            'group_id' => $group->id,
            'status' => 'draft',
            'minutes' => 'هنوز تأیید نشده',
        ]);

        $this->expectException(ValidationException::class);
        app(SecretariatGovernanceIntegrationService::class)->proposeApprovedMeetingMinute($minute, $actor);
    }

    public function test_only_adopted_resolution_creates_draft_and_does_not_duplicate_governance_truth(): void
    {
        [$actor, $group] = $this->groupContext('GOV-S3');
        $proposal = Proposal::query()->create([
            'group_id' => $group->id,
            'created_by' => $actor->id,
            'type' => 'policy',
            'title' => 'مصوبه حفاظت آب',
            'summary' => 'چارچوب مصوب حفاظت آب',
            'description' => 'متن پیشنهادی که در مجمع تصویب شد.',
            'status' => 'approved',
        ]);
        $resolution = Resolution::query()->create([
            'proposal_id' => $proposal->id,
            'group_id' => $group->id,
            'adopted_by' => $actor->id,
            'type' => 'policy',
            'status' => 'adopted',
            'effect_status' => 'pending_bridge',
            'quorum_required_percent' => 20,
            'approval_required_percent' => 60,
            'eligible_voter_count' => 100,
            'votes_cast' => 80,
            'votes_for' => 70,
            'votes_against' => 8,
            'votes_abstain' => 2,
            'financial_effect' => ['kind' => 'test-only'],
            'adopted_at' => now()->subMinute(),
            'effective_at' => now(),
        ]);

        $sourceBefore = $resolution->fresh()->getAttributes();
        $service = app(SecretariatGovernanceIntegrationService::class);
        $record = $service->proposeAdoptedResolution($resolution, $actor);
        $retry = $service->proposeAdoptedResolution($resolution, $actor);

        $this->assertSame($record->id, $retry->id);
        $this->assertSame('draft', $record->status);
        $this->assertSame('resolution', $record->record_type);
        $this->assertSame('governance_resolution', $record->source_type);
        $this->assertSame($resolution->id, $record->source_id);
        $this->assertNull($record->registry_number);

        $snapshot = (array) ($record->metadata['s3_snapshot'] ?? []);
        $this->assertSame($proposal->id, $snapshot['proposal_id']);
        $this->assertSame('adopted', $snapshot['resolution_status']);
        $this->assertArrayNotHasKey('votes_for', $snapshot);
        $this->assertArrayNotHasKey('votes_cast', $snapshot);
        $this->assertArrayNotHasKey('quorum_required_percent', $snapshot);
        $this->assertArrayNotHasKey('effect_status', $snapshot);
        $this->assertSame($sourceBefore, $resolution->fresh()->getAttributes());
    }

    public function test_rejected_resolution_cannot_be_proposed_to_registry(): void
    {
        [$actor, $group] = $this->groupContext('GOV-REJECT-S3');
        $proposal = Proposal::query()->create([
            'group_id' => $group->id,
            'created_by' => $actor->id,
            'type' => 'general',
            'title' => 'پیشنهاد ردشده',
            'status' => 'rejected',
        ]);
        $resolution = Resolution::query()->create([
            'proposal_id' => $proposal->id,
            'group_id' => $group->id,
            'type' => 'general',
            'status' => 'rejected',
            'effect_status' => 'none',
        ]);

        $this->expectException(ValidationException::class);
        app(SecretariatGovernanceIntegrationService::class)->proposeAdoptedResolution($resolution, $actor);
    }

    public function test_resolution_can_be_linked_to_same_office_meeting_minute_as_decision_of(): void
    {
        [$actor, $group] = $this->groupContext('CHAIN-S3');
        $session = GroupSession::query()->create([
            'group_id' => $group->id,
            'created_by' => $actor->id,
            'title' => 'جلسه تصمیم‌گیری',
            'status' => 'ended',
            'starts_at' => now()->subHours(2),
            'ended_at' => now()->subHour(),
        ]);
        $minute = NajmHodaGroupMeetingMinute::query()->create([
            'group_session_id' => $session->id,
            'group_id' => $group->id,
            'status' => 'approved',
            'minutes' => 'صورتجلسه تصمیم‌گیری',
            'approved_by' => $actor->id,
            'approved_at' => now()->subMinutes(40),
        ]);
        $proposal = Proposal::query()->create([
            'group_id' => $group->id,
            'created_by' => $actor->id,
            'type' => 'general',
            'title' => 'تصمیم جلسه',
            'status' => 'approved',
        ]);
        $resolution = Resolution::query()->create([
            'proposal_id' => $proposal->id,
            'group_id' => $group->id,
            'adopted_by' => $actor->id,
            'type' => 'general',
            'status' => 'adopted',
            'effect_status' => 'none',
            'adopted_at' => now()->subMinutes(20),
            'effective_at' => now()->subMinutes(20),
        ]);

        $service = app(SecretariatGovernanceIntegrationService::class);
        $minuteRecord = $service->proposeApprovedMeetingMinute($minute, $actor);
        $resolutionRecord = $service->proposeAdoptedResolution($resolution, $actor, $minuteRecord);

        $this->assertDatabaseHas('secretariat_relations', [
            'source_record_id' => $resolutionRecord->id,
            'target_record_id' => $minuteRecord->id,
            'relation_type' => 'decision_of',
        ]);

        $service->proposeAdoptedResolution($resolution, $actor, $minuteRecord);
        $this->assertSame(1, $resolutionRecord->outgoingRelations()->where('relation_type', 'decision_of')->count());
    }

    /** @return array{0:User,1:Group} */
    private function groupContext(string $code = 'MINUTE-S3'): array
    {
        $actor = User::factory()->create();
        $group = Group::query()->create([
            'name' => 'Secretariat ' . $code,
            'group_type' => '0',
        ]);

        app(SecretariatOfficeService::class)->create([
            'code' => $code,
            'name' => 'Secretariat Office ' . $code,
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);

        return [$actor, $group];
    }
}
