<?php

namespace Tests\Feature\NajmHoda;

use App\Models\EmailTemplate;
use App\Models\FounderEmailDraft;
use App\Models\User;
use App\Services\NajmHoda\FounderOps\FounderEmailDecisionService;
use App\Services\NajmHoda\FounderOps\FounderEmailDraftService;
use App\Services\NajmHoda\FounderOps\FounderEmailTemplateDecisionService;
use App\Services\NajmHoda\FounderOps\FounderExecutiveConnectivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FounderEmailManagementConnectivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_edit_is_persisted_as_review_draft_and_applied_only_after_founder_approval(): void
    {
        $founder = User::factory()->create(['is_system' => false]);
        config(['najm-hoda-founder-action-policy.founder_approval.user_ids' => [$founder->id]]);

        $template = EmailTemplate::query()->create([
            'name' => 'Welcome',
            'subject' => 'Old subject',
            'body' => 'Old body',
            'is_active' => true,
        ]);

        $service = app(FounderEmailTemplateDecisionService::class);
        $prepared = $service->requestEdit($template, [
            'subject' => 'New subject',
            'body' => 'New body',
        ], $founder->id, 'email-template-edit-test-'.$template->id);

        $this->assertSame('awaiting_approval', $prepared['status']);
        $this->assertSame('Old subject', $template->fresh()->subject);

        $requestId = (string) data_get($prepared, 'approval_request.id', '');
        $approved = $service->decideAndExecute($requestId, 'approve', $founder->id, 'Approve template revision');

        $this->assertTrue($approved['success']);
        $this->assertSame('executed', $approved['status']);
        $this->assertSame('New subject', $template->fresh()->subject);
        $this->assertSame('New body', $template->fresh()->body);
        $this->assertDatabaseHas('founder_email_template_drafts', [
            'template_id' => $template->id,
            'status' => 'applied',
            'approved_by' => $founder->id,
        ]);
    }

    public function test_bulk_send_requires_multiple_recipients_and_executes_only_after_approval(): void
    {
        Mail::fake();
        $founder = User::factory()->create(['is_system' => false]);
        config(['najm-hoda-founder-action-policy.founder_approval.user_ids' => [$founder->id]]);

        $draftResult = app(FounderEmailDraftService::class)->draft(
            ['one@example.com', 'two@example.com'],
            null,
            'Bulk subject',
            '<p>Bulk body</p>',
            [],
            'bulk-email-test',
            $founder->id
        );
        $draft = FounderEmailDraft::query()->findOrFail((int) $draftResult['draft_id']);

        $service = app(FounderEmailDecisionService::class);
        $prepared = $service->requestBulkSend($draft, $founder->id);
        $this->assertSame('awaiting_approval', $prepared['status']);
        $this->assertSame('draft', $draft->fresh()->status);

        $requestId = (string) data_get($prepared, 'approval_request.id', '');
        $approved = $service->decideAndExecute($requestId, 'approve', $founder->id, 'Approve bulk email');

        $this->assertTrue($approved['success']);
        $this->assertSame('executed', $approved['status']);
        $this->assertSame('sent', $draft->fresh()->status);
        $this->assertSame(2, (int) data_get($approved, 'result.recipient_count', 0));

        $singleResult = app(FounderEmailDraftService::class)->draft(
            ['single@example.com'],
            null,
            'Single subject',
            'Single body',
            [],
            'single-email-test',
            $founder->id
        );
        $single = FounderEmailDraft::query()->findOrFail((int) $singleResult['draft_id']);
        $blocked = $service->requestBulkSend($single, $founder->id);
        $this->assertFalse($blocked['success']);
        $this->assertSame('bulk_send_requires_multiple_recipients', $blocked['reason']);
    }

    public function test_email_domain_is_managed_when_all_policy_actions_have_real_adapters(): void
    {
        $report = app(FounderExecutiveConnectivityService::class)->report();

        $this->assertSame('managed', data_get($report, 'domains.email.stage'));
        $this->assertSame('connected', data_get($report, 'domains.email.actions.edit_template.state'));
        $this->assertSame('connected', data_get($report, 'domains.email.actions.bulk_send.state'));
    }
}
