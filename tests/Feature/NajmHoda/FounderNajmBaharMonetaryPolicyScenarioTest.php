<?php

namespace Tests\Feature\NajmHoda;

use App\Models\User;
use App\Modules\NajmBahar\Models\MonetaryPolicyVersion;
use App\Modules\NajmBahar\Services\MonetaryPolicyService;
use App\Services\NajmHoda\FounderOps\FounderAcceptanceStatusService;
use App\Services\NajmHoda\FounderOps\FounderExecutiveConnectivityService;
use App\Services\NajmHoda\FounderOps\FounderExecutiveWorkQueueService;
use App\Services\NajmHoda\FounderOps\FounderNajmBaharMonetaryPolicyDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FounderNajmBaharMonetaryPolicyScenarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_founder_approved_policy_draft_becomes_verified_versioned_policy(): void
    {
        $founder = User::factory()->create();
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids', [$founder->id]);
        config()->set('najm-hoda.runtime.autonomy.human_escalation.notify_admins', false);

        $service = app(FounderNajmBaharMonetaryPolicyDecisionService::class);
        $prepared = $service->prepare([
            'reputation_conversion_enabled' => true,
            'reputation_to_gol_ratio' => 250,
        ], 'Founder scenario: approve a versioned reputation conversion policy.');

        $version = MonetaryPolicyVersion::query()->findOrFail((int) $prepared['policy_version_id']);
        $this->assertSame('draft', $version->status);
        $this->assertNull($version->approved_at);
        $this->assertSame(250, (int) data_get($version->parameters, 'reputation_to_gol_ratio'));
        $this->assertSame('legacy_settings', app(MonetaryPolicyService::class)->current()['source']);

        $connectivity = app(FounderExecutiveConnectivityService::class)->report();
        $this->assertSame(
            'connected',
            data_get($connectivity, 'domains.najm_bahar.actions.change_monetary_policy.state')
        );
        $this->assertNull(data_get($connectivity, 'domains.najm_bahar.actions.change_monetary_policy.block'));

        $requested = $service->requestActivate($version, $founder->id);
        $this->assertSame('awaiting_approval', $requested['status']);
        $requestId = (string) data_get($requested, 'approval_request.id');
        $this->assertNotSame('', $requestId);

        $queue = app(FounderExecutiveWorkQueueService::class)->snapshot(24, 100);
        $approvalItem = collect($queue['items'] ?? [])->first(
            fn (array $item): bool => ($item['kind'] ?? null) === 'approval'
                && ($item['domain'] ?? null) === 'najm_bahar'
                && ($item['action'] ?? null) === 'change_monetary_policy'
                && (string) ($item['approval_request_id'] ?? '') === $requestId
        );
        $this->assertNotNull($approvalItem);

        $executed = $service->decideAndExecute(
            $requestId,
            'approve',
            $founder->id,
            'Explicit founder approval for the versioned monetary policy.'
        );

        $this->assertTrue((bool) ($executed['success'] ?? false));
        $this->assertSame('executed', $executed['status']);
        $this->assertTrue((bool) data_get($executed, 'verification.verified'));
        $this->assertSame('verified', data_get($executed, 'verification.status'));

        $version->refresh();
        $this->assertSame('active', $version->status);
        $this->assertSame($founder->id, (int) $version->approved_by);
        $this->assertNotNull($version->approved_at);
        $this->assertNotNull($version->effective_from);

        $current = app(MonetaryPolicyService::class)->current();
        $this->assertSame('versioned_policy', $current['source']);
        $this->assertSame($version->id, $current['version_id']);
        $this->assertSame(250, (int) data_get($current, 'parameters.reputation_to_gol_ratio'));

        $acceptance = app(FounderAcceptanceStatusService::class)->snapshot(100);
        $verified = collect($acceptance['items'] ?? [])->first(
            fn (array $item): bool => ($item['domain'] ?? null) === 'najm_bahar'
                && ($item['action'] ?? null) === 'change_monetary_policy'
                && ($item['acceptance'] ?? null) === 'verified'
        );
        $this->assertNotNull($verified);

        $finalQueue = app(FounderExecutiveWorkQueueService::class)->snapshot(24, 100);
        $stillAwaitingApproval = collect($finalQueue['items'] ?? [])->contains(
            fn (array $item): bool => ($item['kind'] ?? null) === 'approval'
                && (string) ($item['approval_request_id'] ?? '') === $requestId
        );
        $this->assertFalse($stillAwaitingApproval);
    }

    public function test_founder_policy_boundary_refuses_to_enable_idle_tax_collection(): void
    {
        $this->expectException(ValidationException::class);

        app(FounderNajmBaharMonetaryPolicyDecisionService::class)->prepare([
            'idle_tax_enabled' => true,
            'idle_tax_rate_bps' => 2000,
        ], 'Attempt to enable collection before the canonical idle-tax collector exists.');
    }
}
