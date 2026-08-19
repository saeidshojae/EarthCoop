<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupSession;
use App\Models\GroupUser;
use App\Models\NajmHodaGroupActionItem;
use App\Models\NajmHodaGroupMeetingMinute;
use App\Models\User;
use App\Modules\Governance\Models\Proposal;
use App\Modules\Governance\Models\Resolution;
use App\Modules\Secretariat\Models\SecretariatRecord;
use App\Modules\Secretariat\Services\SecretariatGovernanceIntegrationService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use App\Services\NajmHoda\Context\NajmHodaPageContextResolver;
use App\Services\NajmHoda\Context\NajmHodaSecretariatExecutionReportAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmHodaSecretariatExecutionReportAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_is_pure_and_confirmation_creates_one_evidence_linked_draft(): void
    {
        [$actor, $office, $action, $resolutionRecord] = $this->evidenceChain('S7-EXEC');
        $pageContext = $this->pageContext($actor, $office->id);
        $assistant = app(NajmHodaSecretariatExecutionReportAssistant::class);

        $before = SecretariatRecord::query()->count();
        $preview = $assistant->intercept(
            $actor,
            $pageContext,
            "گزارش اجرای مصوبه آماده کن | اقدام: {$action->id} | رکورد مصوبه: {$resolutionRecord->id}",
            9401,
        );

        $this->assertIsArray($preview);
        $this->assertSame('secretariat_execution_report', $preview['agent']);
        $this->assertSame('awaiting_confirmation', $preview['status']);
        $this->assertSame($before, SecretariatRecord::query()->count());
        $this->assertStringContainsString((string) $resolutionRecord->registry_number, $preview['message']);

        $saved = $assistant->intercept($actor, $pageContext, 'ذخیره گزارش اجرا', 9401);
        $this->assertSame('draft_saved', $saved['status']);

        $report = SecretariatRecord::query()
            ->where('record_type', 'execution_record')
            ->where('source_type', 'action_item')
            ->where('source_id', $action->id)
            ->sole();

        $this->assertSame('draft', $report->status);
        $this->assertNull($report->registry_number);
        $this->assertSame($action->details, $report->currentVersion->body);
        $this->assertDatabaseHas('secretariat_relations', [
            'source_record_id' => $report->id,
            'target_record_id' => $resolutionRecord->id,
            'relation_type' => 'report_of',
        ]);
    }

    public function test_stale_or_broken_evidence_never_creates_report(): void
    {
        [$actor, $office, $action, $resolutionRecord] = $this->evidenceChain('S7-EXEC-STALE');
        $assistant = app(NajmHodaSecretariatExecutionReportAssistant::class);
        $pageContext = $this->pageContext($actor, $office->id);

        $preview = $assistant->intercept(
            $actor,
            $pageContext,
            "گزارش اجرای مصوبه آماده کن | اقدام: {$action->id} | رکورد مصوبه: {$resolutionRecord->id}",
            9402,
        );
        $this->assertSame('awaiting_confirmation', $preview['status']);

        $action->forceFill([
            'details' => 'شواهد اجرای اقدام پس از پیش‌نمایش تغییر کرد.',
            'updated_at' => now()->addSecond(),
        ])->save();

        $stale = $assistant->intercept($actor, $pageContext, 'ذخیره گزارش اجرا', 9402);
        $this->assertSame('stale_preview', $stale['status']);
        $this->assertSame(0, SecretariatRecord::query()->where('source_type', 'action_item')->where('source_id', $action->id)->count());

        $broken = NajmHodaGroupActionItem::query()->create([
            'group_id' => $action->group_id,
            'title' => 'اقدام بدون منشأ رسمی',
            'details' => 'انجام شد اما provenance ندارد.',
            'status' => 'done',
            'meta' => [],
        ]);
        $blocked = $assistant->intercept(
            $actor,
            $pageContext,
            "گزارش اجرای مصوبه آماده کن | اقدام: {$broken->id} | رکورد مصوبه: {$resolutionRecord->id}",
            9403,
        );
        $this->assertSame('blocked', $blocked['status']);
    }

    private function evidenceChain(string $code): array
    {
        $actor = User::factory()->create();
        $group = Group::query()->create(['name' => $code, 'group_type' => '0']);
        GroupUser::query()->create([
            'group_id' => $group->id,
            'user_id' => $actor->id,
            'role' => 3,
            'status' => 1,
            'expired' => null,
        ]);
        $office = app(SecretariatOfficeService::class)->create([
            'code' => $code,
            'name' => $code . ' Office',
            'office_type' => 'group',
            'scope_type' => 'group',
            'scope_id' => $group->id,
        ]);

        $session = GroupSession::query()->create([
            'group_id' => $group->id,
            'created_by' => $actor->id,
            'title' => 'جلسه اجرای مصوبه',
            'status' => 'ended',
            'starts_at' => now()->subHours(2),
            'ended_at' => now()->subHour(),
        ]);
        $minute = NajmHodaGroupMeetingMinute::query()->create([
            'group_session_id' => $session->id,
            'group_id' => $group->id,
            'status' => 'approved',
            'summary' => 'صورتجلسه رسمی اجرای مصوبه',
            'minutes' => 'اقدام اجرایی در این جلسه تصویب شد.',
            'approved_by' => $actor->id,
            'approved_at' => now()->subMinutes(40),
        ]);
        $proposal = Proposal::query()->create([
            'group_id' => $group->id,
            'created_by' => $actor->id,
            'type' => 'general',
            'title' => 'مصوبه اقدام اجرایی',
            'summary' => 'اجرای اقدام مشخص',
            'description' => 'شرح مصوبه اجرایی',
            'status' => 'approved',
        ]);
        $resolution = Resolution::query()->create([
            'proposal_id' => $proposal->id,
            'group_id' => $group->id,
            'adopted_by' => $actor->id,
            'type' => 'general',
            'status' => 'adopted',
            'effect_status' => 'none',
            'adopted_at' => now()->subMinutes(30),
            'effective_at' => now()->subMinutes(30),
        ]);

        $integration = app(SecretariatGovernanceIntegrationService::class);
        $minuteDraft = $integration->proposeApprovedMeetingMinute($minute, $actor);
        $resolutionDraft = $integration->proposeAdoptedResolution($resolution, $actor, $minuteDraft);
        $records = app(SecretariatRecordService::class);
        $minuteRecord = $records->register($records->submitForApproval($minuteDraft, $actor), $actor);
        $resolutionRecord = $records->register($records->submitForApproval($resolutionDraft, $actor), $actor);

        $action = NajmHodaGroupActionItem::query()->create([
            'group_id' => $group->id,
            'title' => 'اجرای اقدام مصوب',
            'details' => 'اقدام طبق صورتجلسه انجام شد و نتیجه ثبت گردید.',
            'status' => 'done',
            'priority' => 'normal',
            'meta' => ['meeting_minute_id' => $minute->id],
        ]);

        return [$actor, $office, $action, $resolutionRecord, $minuteRecord];
    }

    private function pageContext(User $actor, int $officeId): array
    {
        return app(NajmHodaPageContextResolver::class)->resolve($actor, [
            'page' => [
                'route_name' => 'secretariat.index',
                'module' => 'secretariat',
                'resource_type' => 'secretariat_office',
                'resource_id' => $officeId,
                'title' => 'FORGED TITLE',
                'body' => 'FORGED BODY',
            ],
        ]);
    }
}
