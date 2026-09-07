<?php

namespace Tests\Feature\NajmHoda;

use App\Models\FounderNajmBaharTransactionIntent;
use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Services\NajmHoda\FounderOps\FounderAcceptanceStatusService;
use App\Services\NajmHoda\FounderOps\FounderExecutiveConnectivityService;
use App\Services\NajmHoda\FounderOps\FounderExecutiveWorkQueueService;
use App\Services\NajmHoda\FounderOps\FounderNajmBaharTransactionDecisionService;
use App\Services\NajmHoda\FounderOps\FounderNajmBaharTransactionIntentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FounderNajmBaharFinancialScenarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_founder_approved_active_bahar_intent_reaches_canonical_ledger_and_verified_acceptance(): void
    {
        $founder = User::factory()->create();
        config()->set('najm-hoda-founder-action-policy.founder_approval.user_ids', [$founder->id]);
        config()->set('najm-hoda.runtime.autonomy.human_escalation.notify_admins', false);

        $source = Account::query()->create([
            'account_number' => 'FOUNDER-FIN-SYSTEM-001',
            'name' => 'Founder financial scenario source',
            'type' => 'system',
            'status' => 1,
            'balance' => 1_000,
            'balance_active' => 1_000,
            'balance_faded' => 0,
        ]);
        $destination = Account::query()->create([
            'account_number' => 'FOUNDER-FIN-USER-001',
            'name' => 'Founder financial scenario destination',
            'type' => 'user',
            'status' => 1,
            'balance' => 100,
            'balance_active' => 100,
            'balance_faded' => 0,
        ]);

        $intentResult = app(FounderNajmBaharTransactionIntentService::class)->prepare(
            $source,
            $destination,
            250,
            'founder-financial-scenario-' . $founder->id,
            $founder->id,
            'Founder-approved operational Active Bahar transfer',
            'founder_financial_scenario',
            ['scenario' => 'founder_acceptance']
        );

        $intent = FounderNajmBaharTransactionIntent::query()->findOrFail((int) $intentResult['intent_id']);
        $this->assertSame('draft', $intent->status);
        $this->assertSame('active', $intent->balance_type);
        $this->assertNull($intent->transaction_id);
        $this->assertSame(1_000, (int) $source->fresh()->balance_active);
        $this->assertSame(100, (int) $destination->fresh()->balance_active);

        $connectivity = app(FounderExecutiveConnectivityService::class)->report();
        $this->assertSame(
            'connected',
            data_get($connectivity, 'domains.najm_bahar.actions.execute_transaction.state')
        );
        $this->assertNull(data_get($connectivity, 'domains.najm_bahar.actions.execute_transaction.block'));

        $decision = app(FounderNajmBaharTransactionDecisionService::class);
        $requested = $decision->requestExecute($intent, $founder->id);

        $this->assertSame('awaiting_approval', $requested['status']);
        $requestId = (string) data_get($requested, 'approval_request.id');
        $this->assertNotSame('', $requestId);
        $this->assertNull($intent->fresh()->transaction_id);

        $queue = app(FounderExecutiveWorkQueueService::class)->snapshot(24, 100);
        $approvalItem = collect($queue['items'] ?? [])->first(
            fn (array $item): bool => ($item['kind'] ?? null) === 'approval'
                && ($item['domain'] ?? null) === 'najm_bahar'
                && ($item['action'] ?? null) === 'execute_transaction'
                && (string) ($item['approval_request_id'] ?? '') === $requestId
        );
        $this->assertNotNull($approvalItem);

        $executed = $decision->decideAndExecute(
            $requestId,
            'approve',
            $founder->id,
            'Founder financial scenario approval'
        );

        $this->assertTrue((bool) ($executed['success'] ?? false));
        $this->assertSame('executed', $executed['status']);
        $this->assertTrue((bool) data_get($executed, 'verification.verified'));
        $this->assertSame('verified', data_get($executed, 'verification.status'));

        $intent->refresh();
        $source->refresh();
        $destination->refresh();

        $this->assertSame('executed', $intent->status);
        $this->assertNotNull($intent->transaction_id);
        $this->assertSame(750, (int) $source->balance_active);
        $this->assertSame(350, (int) $destination->balance_active);
        $this->assertDatabaseHas('najm_transactions', [
            'id' => $intent->transaction_id,
            'from_account_id' => $source->id,
            'to_account_id' => $destination->id,
            'amount' => 250,
        ]);

        $acceptance = app(FounderAcceptanceStatusService::class)->snapshot(100);
        $verified = collect($acceptance['items'] ?? [])->first(
            fn (array $item): bool => ($item['domain'] ?? null) === 'najm_bahar'
                && ($item['action'] ?? null) === 'execute_transaction'
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
}
