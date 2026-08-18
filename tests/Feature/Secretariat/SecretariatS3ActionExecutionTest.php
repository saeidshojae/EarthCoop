<?php

namespace Tests\Feature\Secretariat;

use App\Models\Group;
use App\Models\GroupSession;
use App\Models\NajmHodaGroupActionItem;
use App\Models\NajmHodaGroupMeetingMinute;
use App\Models\User;
use App\Modules\Governance\Models\Proposal;
use App\Modules\Governance\Models\Resolution;
use App\Modules\Secretariat\Services\SecretariatGovernanceIntegrationService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SecretariatS3ActionExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_session_to_execution_report_chain_is_auditable_and_preserves_source_ownership(): void
    {
        [$actor, $group] = $this->groupContext('S3-E2E');
        $integration = app(SecretariatGovernanceIntegrationService::class);
        $records = app(SecretariatRecordService::class);

        $session = GroupSession::query()->create([
            'group_id' => $group->id,
            'created_by' => $actor->id,
            'title' => 'جلسه اجرای مصوبه',
            'subject' => 'تصمیم و پیگیری اجرا',
            'status' => 'ended',
            'starts_at' => now()->subHours(3),
            'started_at' => now()->subHours(3),
            'ended_at' => now()->subHours(2),
        ]);
        $minute = NajmHodaGroupMeetingMinute::query()->create([
            'group_session_id' => $session->id,
            'group_id' => $group->id,
            'status' => 'approved',
            'summary' => 'صورتجلسه رسمی',
            'minutes' => 'مصوبه اجرا و مسئول پیگیری در جلسه ثبت شد.',
            'approved_by' => $actor->id,
            'approved_at' => now()->subHours(2),
        ]);

        $minuteRecord = $integration->proposeApprovedMeetingMinute($minute, $actor);
        $minuteRecord = $records->register($records->submitForApproval($minuteRecord, $actor), $actor);
        $this->assertNotNull($minuteRecord->registry_number);

        $proposal = Proposal::query()->create([
            'group_id' => $group->id,
            'created_by' => $actor->id,
            'type' => 'general',
            'title' => 'مصوبه اجرای برنامه',
            'summary' => 'مصوبه‌ای که باید اجرا شود',
            'description' => 'متن رسمی تصمیم مجمع.',
            'status' => 'approved',
        ]);
        $resolution = Resolution::query()->create([
            'proposal_id' => $proposal->id,
            'group_id' => $group->id,
            'adopted_by' => $actor->id,
            'type' => 'general',
            'status' => 'adopted',
            'effect_status' => 'none',
            'adopted_at' => now()->subHour(),
            'effective_at' => now()->subHour(),
        ]);

        $resolutionRecord = $integration->proposeAdoptedResolution($resolution, $actor, $minuteRecord);
        $resolutionRecord = $records->register($records->submitForApproval($resolutionRecord, $actor), $actor);
        $this->assertNotNull($resolutionRecord->registry_number);

        $action = NajmHodaGroupActionItem::query()->create([
            'group_id' => $group->id,
            'assigned_user_id' => $actor->id,
            'title' => 'اجرای برنامه مصوب',
            'details' => 'اقدام مصوب انجام و نتیجه مستندسازی شد.',
            'priority' => 'high',
            'status' => 'done',
            'meta' => [
                'origin' => 'najm_hoda_meeting_minutes',
                'meeting_minute_id' => $minute->id,
                'group_session_id' => $session->id,
            ],
        ]);
        $actionBefore = $action->fresh()->getAttributes();

        $report = $integration->proposeCompletedActionExecutionReport($action, $resolutionRecord, $actor);
        $retry = $integration->proposeCompletedActionExecutionReport($action, $resolutionRecord, $actor);

        $this->assertSame($report->id, $retry->id);
        $this->assertSame('draft', $report->status);
        $this->assertSame('execution_record', $report->record_type);
        $this->assertSame('action_item', $report->source_type);
        $this->assertSame($action->id, $report->source_id);
        $this->assertNull($report->registry_number);
        $this->assertSame($actionBefore, $action->fresh()->getAttributes());
        $this->assertSame($minute->id, (int) data_get($report->metadata, 's3_snapshot.meeting_minute_id'));
        $this->assertSame($minuteRecord->id, (int) data_get($report->metadata, 's3_snapshot.meeting_minute_record_id'));

        $this->assertDatabaseHas('secretariat_relations', [
            'source_record_id' => $resolutionRecord->id,
            'target_record_id' => $minuteRecord->id,
            'relation_type' => 'decision_of',
        ]);
        $this->assertDatabaseHas('secretariat_relations', [
            'source_record_id' => $report->id,
            'target_record_id' => $resolutionRecord->id,
            'relation_type' => 'report_of',
        ]);
        $this->assertSame(1, $report->outgoingRelations()->where('relation_type', 'report_of')->count());

        $this->assertDatabaseHas('secretariat_audit_events', [
            'record_id' => $minuteRecord->id,
            'event_type' => 'registered',
        ]);
        $this->assertDatabaseHas('secretariat_audit_events', [
            'record_id' => $resolutionRecord->id,
            'event_type' => 'registered',
        ]);
        $this->assertDatabaseHas('secretariat_audit_events', [
            'record_id' => $report->id,
            'event_type' => 'relation_added',
        ]);
    }

    public function test_open_action_item_cannot_produce_execution_report(): void
    {
        [$actor, $group] = $this->groupContext('S3-ACTION-OPEN');
        $resolutionRecord = $this->registeredResolution($actor, $group);
        $action = NajmHodaGroupActionItem::query()->create([
            'group_id' => $group->id,
            'title' => 'اقدام هنوز باز',
            'priority' => 'medium',
            'status' => 'open',
        ]);

        $this->expectException(ValidationException::class);
        app(SecretariatGovernanceIntegrationService::class)
            ->proposeCompletedActionExecutionReport($action, $resolutionRecord, $actor);
    }

    public function test_done_action_requires_formally_registered_resolution_record(): void
    {
        [$actor, $group] = $this->groupContext('S3-RES-DRAFT');
        $proposal = Proposal::query()->create([
            'group_id' => $group->id,
            'created_by' => $actor->id,
            'type' => 'general',
            'title' => 'مصوبه هنوز ثبت‌نشده',
            'status' => 'approved',
        ]);
        $resolution = Resolution::query()->create([
            'proposal_id' => $proposal->id,
            'group_id' => $group->id,
            'adopted_by' => $actor->id,
            'type' => 'general',
            'status' => 'adopted',
            'effect_status' => 'none',
            'adopted_at' => now(),
            'effective_at' => now(),
        ]);
        $resolutionDraft = app(SecretariatGovernanceIntegrationService::class)
            ->proposeAdoptedResolution($resolution, $actor);
        $action = NajmHodaGroupActionItem::query()->create([
            'group_id' => $group->id,
            'title' => 'اقدام انجام‌شده',
            'priority' => 'medium',
            'status' => 'done',
        ]);

        $this->expectException(ValidationException::class);
        app(SecretariatGovernanceIntegrationService::class)
            ->proposeCompletedActionExecutionReport($action, $resolutionDraft, $actor);
    }

    public function test_done_action_cannot_be_attached_to_unrelated_same_group_resolution(): void
    {
        [$actor, $group] = $this->groupContext('S3-PROVENANCE');
        $records = app(SecretariatRecordService::class);
        $integration = app(SecretariatGovernanceIntegrationService::class);

        $session = GroupSession::query()->create([
            'group_id' => $group->id,
            'created_by' => $actor->id,
            'title' => 'جلسه مرجع اقدام',
            'status' => 'ended',
            'starts_at' => now()->subHours(2),
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subHour(),
        ]);
        $minute = NajmHodaGroupMeetingMinute::query()->create([
            'group_session_id' => $session->id,
            'group_id' => $group->id,
            'status' => 'approved',
            'summary' => 'صورتجلسه مرجع اقدام',
            'approved_by' => $actor->id,
            'approved_at' => now()->subHour(),
        ]);
        $minuteRecord = $integration->proposeApprovedMeetingMinute($minute, $actor);
        $records->register($records->submitForApproval($minuteRecord, $actor), $actor);

        $unrelatedResolutionRecord = $this->registeredResolution($actor, $group);
        $action = NajmHodaGroupActionItem::query()->create([
            'group_id' => $group->id,
            'title' => 'اقدام انجام‌شده همان جلسه',
            'priority' => 'medium',
            'status' => 'done',
            'meta' => [
                'origin' => 'najm_hoda_meeting_minutes',
                'meeting_minute_id' => $minute->id,
                'group_session_id' => $session->id,
            ],
        ]);

        $this->expectException(ValidationException::class);
        $integration->proposeCompletedActionExecutionReport($action, $unrelatedResolutionRecord, $actor);
    }

    public function test_done_action_without_meeting_minute_provenance_is_rejected(): void
    {
        [$actor, $group] = $this->groupContext('S3-NO-PROVENANCE');
        $resolutionRecord = $this->registeredResolution($actor, $group);
        $action = NajmHodaGroupActionItem::query()->create([
            'group_id' => $group->id,
            'title' => 'اقدام عمومی بدون منشأ جلسه',
            'priority' => 'medium',
            'status' => 'done',
        ]);

        $this->expectException(ValidationException::class);
        app(SecretariatGovernanceIntegrationService::class)
            ->proposeCompletedActionExecutionReport($action, $resolutionRecord, $actor);
    }

    private function registeredResolution(User $actor, Group $group)
    {
        $proposal = Proposal::query()->create([
            'group_id' => $group->id,
            'created_by' => $actor->id,
            'type' => 'general',
            'title' => 'مصوبه مرجع',
            'status' => 'approved',
        ]);
        $resolution = Resolution::query()->create([
            'proposal_id' => $proposal->id,
            'group_id' => $group->id,
            'adopted_by' => $actor->id,
            'type' => 'general',
            'status' => 'adopted',
            'effect_status' => 'none',
            'adopted_at' => now(),
            'effective_at' => now(),
        ]);
        $draft = app(SecretariatGovernanceIntegrationService::class)
            ->proposeAdoptedResolution($resolution, $actor);
        $records = app(SecretariatRecordService::class);

        return $records->register($records->submitForApproval($draft, $actor), $actor);
    }

    /** @return array{0:User,1:Group} */
    private function groupContext(string $code): array
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
