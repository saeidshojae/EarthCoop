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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NajmHodaSecretariatExecutionReportChatRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_api_previews_then_creates_grounded_execution_report_draft_only(): void
    {
        config(['najm-hoda.enabled' => true]);
        [$actor, $office, $action, $resolutionRecord] = $this->evidenceChain('S7-EXEC-RUNTIME');
        $this->actingAs($actor);

        $page = [
            'route_name' => 'secretariat.index',
            'module' => 'secretariat',
            'resource_type' => 'secretariat_office',
            'resource_id' => $office->id,
            'title' => 'BROWSER FORGED TITLE',
            'body' => 'BROWSER FORGED BODY',
        ];

        $before = SecretariatRecord::query()->count();
        $preview = $this->postJson('/api/najm-hoda/chat', [
            'message' => "گزارش اجرای مصوبه آماده کن | اقدام: {$action->id} | رکورد مصوبه: {$resolutionRecord->id}",
            'context' => ['page' => $page],
        ]);
        $preview->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('agent', 'secretariat_execution_report');
        $this->assertSame($before, SecretariatRecord::query()->count());
        $this->assertStringContainsString((string) $resolutionRecord->registry_number, (string) $preview->json('message'));
        $this->assertStringNotContainsString('BROWSER FORGED BODY', (string) $preview->json('message'));

        $saved = $this->postJson('/api/najm-hoda/chat', [
            'message' => 'ذخیره گزارش اجرا',
            'conversation_id' => (int) $preview->json('conversation_id'),
            'context' => ['page' => $page],
        ]);
        $saved->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('agent', 'secretariat_execution_report');
        $this->assertStringContainsString('Draft', (string) $saved->json('message'));

        $report = SecretariatRecord::query()
            ->where('record_type', 'execution_record')
            ->where('source_type', 'action_item')
            ->where('source_id', $action->id)
            ->sole();
        $this->assertSame('draft', $report->status);
        $this->assertNull($report->registry_number);
        $this->assertSame($action->details, $report->currentVersion->body);
        $this->assertSame(0, $report->dispatches()->count());
    }

    private function evidenceChain(string $code): array
    {
        $actor = User::factory()->create();
        $group = Group::query()->create(['name' => $code, 'group_type' => '0']);
        GroupUser::query()->create(['group_id' => $group->id, 'user_id' => $actor->id, 'role' => 3, 'status' => 1, 'expired' => null]);
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
            'summary' => 'صورتجلسه رسمی',
            'minutes' => 'متن صورتجلسه رسمی',
            'approved_by' => $actor->id,
            'approved_at' => now()->subMinutes(40),
        ]);
        $proposal = Proposal::query()->create([
            'group_id' => $group->id,
            'created_by' => $actor->id,
            'type' => 'general',
            'title' => 'مصوبه اجرایی',
            'summary' => 'خلاصه مصوبه',
            'description' => 'شرح مصوبه',
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
        $records->register($records->submitForApproval($minuteDraft, $actor), $actor);
        $resolutionRecord = $records->register($records->submitForApproval($resolutionDraft, $actor), $actor);

        $action = NajmHodaGroupActionItem::query()->create([
            'group_id' => $group->id,
            'title' => 'اجرای مصوبه',
            'details' => 'اقدام بر پایه صورتجلسه انجام و نتیجه مستند شد.',
            'status' => 'done',
            'priority' => 'normal',
            'meta' => ['meeting_minute_id' => $minute->id],
        ]);

        return [$actor, $office, $action, $resolutionRecord];
    }
}
