<?php

namespace Tests\Feature\NajmHoda;

use App\Models\User;
use App\Modules\Secretariat\Services\SecretariatCaseService;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Modules\Secretariat\Services\SecretariatRecordService;
use App\Services\NajmHoda\FounderOps\FounderExecutiveConnectivityService;
use App\Services\NajmHoda\FounderOps\FounderLowRiskDomainActionService;
use App\Services\NajmHoda\FounderOps\FounderSecretariatDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FounderSecretariatDecisionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_founder_approval_registers_formal_record_through_canonical_registry(): void
    {
        $founder = User::factory()->create(['is_system' => false]);
        config(['najm-hoda-founder-action-policy.founder_approval.user_ids' => [$founder->id]]);

        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'NH-CENTRAL',
            'name' => 'Najm Hoda Central Secretariat',
            'office_type' => 'central',
        ]);
        $records = app(SecretariatRecordService::class);
        $record = $records->createDraft($office, $founder, [
            'record_type' => 'official_note',
            'title' => 'Founder Ops formal note',
            'body' => 'Formal body',
        ]);
        $record = $records->submitForApproval($record, $founder);

        $service = app(FounderSecretariatDecisionService::class);
        $prepared = $service->requestRegister($record, $founder->id, 'secretariat-register-test-'.$record->id);

        $this->assertSame('awaiting_approval', $prepared['status']);
        $this->assertNull($record->fresh()->registry_number);

        $requestId = (string) data_get($prepared, 'approval_request.id', '');
        $result = $service->decideAndExecute($requestId, 'approve', $founder->id, 'Founder approved formal registration');

        $this->assertTrue($result['success']);
        $this->assertSame('executed', $result['status']);
        $this->assertSame('registered', $record->fresh()->status);
        $this->assertNotNull($record->fresh()->registry_number);
        $this->assertSame(
            'connected',
            data_get(app(FounderExecutiveConnectivityService::class)->report(), 'domains.secretariat.actions.register_formal_record.state')
        );
    }

    public function test_rejecting_close_case_keeps_case_open_and_approval_by_other_user_is_forbidden(): void
    {
        $founder = User::factory()->create(['is_system' => false]);
        $other = User::factory()->create(['is_system' => false]);
        config(['najm-hoda-founder-action-policy.founder_approval.user_ids' => [$founder->id]]);

        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'NH-CASE',
            'name' => 'Najm Hoda Case Office',
            'office_type' => 'central',
        ]);
        $case = app(SecretariatCaseService::class)->create($office, $founder, [
            'title' => 'Founder Ops case',
        ]);

        $service = app(FounderSecretariatDecisionService::class);
        $prepared = $service->requestCloseCase($case, $founder->id, 'secretariat-close-case-test-'.$case->id);
        $requestId = (string) data_get($prepared, 'approval_request.id', '');

        $forbidden = $service->decideAndExecute($requestId, 'approve', $other->id);
        $this->assertFalse($forbidden['success']);
        $this->assertSame('forbidden', $forbidden['status']);
        $this->assertSame('open', $case->fresh()->status);

        $rejected = $service->decideAndExecute($requestId, 'reject', $founder->id, 'Keep case open');
        $this->assertTrue($rejected['success']);
        $this->assertSame('rejected', $rejected['status']);
        $this->assertSame('open', $case->fresh()->status);
        $this->assertNull($case->fresh()->closed_at);
        $this->assertSame(
            'connected',
            data_get(app(FounderExecutiveConnectivityService::class)->report(), 'domains.secretariat.actions.close_case.state')
        );
    }

    public function test_delegated_safe_correspondence_action_creates_only_a_draft(): void
    {
        $actor = User::factory()->create(['is_system' => false]);
        $recipient = User::factory()->create(['is_system' => false]);
        $office = app(SecretariatOfficeService::class)->create([
            'code' => 'NH-DRAFT',
            'name' => 'Najm Hoda Draft Office',
            'office_type' => 'central',
        ]);

        $service = app(FounderLowRiskDomainActionService::class);
        $result = $service->execute('secretariat', 'draft_correspondence', [
            'office_id' => $office->id,
            'requested_by' => $actor->id,
            'direction' => 'internal',
            'attributes' => [
                'title' => 'Draft correspondence',
                'subject' => 'Operational follow-up',
                'body' => 'Draft only; not registered or dispatched.',
            ],
            'parties' => [
                [
                    'role' => 'sender',
                    'party_type' => 'user',
                    'user_id' => $actor->id,
                    'display_name' => $actor->email,
                ],
                [
                    'role' => 'recipient',
                    'party_type' => 'user',
                    'user_id' => $recipient->id,
                    'display_name' => $recipient->email,
                ],
            ],
            'reason_code' => 'secretariat-draft-test',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('draft_ready', $result['status']);
        $record = \App\Modules\Secretariat\Models\SecretariatRecord::query()->findOrFail((int) $result['record_id']);
        $this->assertSame('draft', $record->status);
        $this->assertNull($record->registry_number);
        $this->assertSame(0, $record->dispatches()->count());
    }

    public function test_secretariat_reports_transport_dependency_instead_of_fake_missing_adapter(): void
    {
        $report = app(FounderExecutiveConnectivityService::class)->report();

        $this->assertSame('partial', data_get($report, 'domains.secretariat.stage'));
        $this->assertSame('connected', data_get($report, 'domains.secretariat.actions.draft_correspondence.state'));
        $this->assertSame('blocked_dependency', data_get($report, 'domains.secretariat.actions.dispatch_formal_record.state'));
        $this->assertSame(
            'real_transport_not_available',
            data_get($report, 'domains.secretariat.actions.dispatch_formal_record.block.reason')
        );
        $this->assertSame('protected', data_get($report, 'domains.secretariat.actions.rewrite_history.state'));
        $this->assertSame('managed', data_get($report, 'domains.users.stage'));
        $this->assertSame('blocked_dependency', data_get($report, 'domains.governance.actions.change_election_rules.state'));
        $this->assertSame('blocked_dependency', data_get($report, 'domains.notifications.actions.change_global_notification_defaults.state'));
    }
}
