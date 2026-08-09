<?php

namespace Tests\Feature\Governance;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Poll;
use App\Models\User;
use App\Modules\Governance\Models\Proposal;
use App\Modules\Governance\Models\Resolution;
use App\Modules\Governance\Services\EligibilitySnapshotService;
use App\Modules\Governance\Services\PublicContributionService;
use App\Modules\Governance\Services\PublicExecutionAuthorizationService;
use App\Modules\Governance\Services\PublicExecutionBridge;
use App\Modules\Governance\Services\PublicExecutionPaymentApprovalService;
use App\Modules\Governance\Services\PublicExecutionPaymentInstructionService;
use App\Modules\Governance\Services\PublicExecutionReversalRequestService;
use App\Modules\Governance\Services\ResolutionEconomicBridge;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\GovernanceExecutionOutboxConsumer;
use App\Modules\NajmBahar\Services\PublicExecutionPaymentService;
use App\Modules\NajmBahar\Services\PublicExecutionReversalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicExecutionReversalExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_reversal_returns_active_bahar_once(): void
    {
        [$payment, $manager, $inspector, $executionAccount, $payeeAccount] = $this->executedPayment(10, 6);
        $reversal = app(PublicExecutionReversalRequestService::class)->create(
            $payment, 3, 'Partial refund', $manager, 'reversal-success:' . $payment->id
        );
        $approved = app(PublicExecutionReversalRequestService::class)->approve($reversal, $inspector);
        $transactionsBefore = Transaction::count();

        $service = app(PublicExecutionReversalService::class);
        $executed = $service->execute($approved);

        $this->assertSame('executed', $executed->status);
        $this->assertSame(7, (int) $executionAccount->fresh()->balance_active);
        $this->assertSame(3, (int) $payeeAccount->fresh()->balance_active);
        $this->assertSame($transactionsBefore + 1, Transaction::count());

        $service->execute($approved->fresh());
        $this->assertSame(7, (int) $executionAccount->fresh()->balance_active);
        $this->assertSame(3, (int) $payeeAccount->fresh()->balance_active);
        $this->assertSame($transactionsBefore + 1, Transaction::count());
    }

    public function test_reversal_with_insufficient_payee_funds_dead_letters_then_recovers(): void
    {
        [$payment, $manager, $inspector, $executionAccount, $payeeAccount] = $this->executedPayment(10, 6);
        $reversal = app(PublicExecutionReversalRequestService::class)->create(
            $payment, 5, 'Refund after contract dispute', $manager, 'reversal-failure:' . $payment->id
        );
        $approved = app(PublicExecutionReversalRequestService::class)->approve($reversal, $inspector);

        $payeeAccount->balance_active = 0;
        $payeeAccount->balance = (int) ($payeeAccount->balance_faded ?? 0) + (int) ($payeeAccount->committed_dim ?? 0);
        $payeeAccount->save();

        $transactionsBefore = Transaction::count();
        $service = app(PublicExecutionReversalService::class);
        for ($attempt = 1; $attempt <= PublicExecutionReversalService::MAX_ATTEMPTS; $attempt++) {
            try {
                $service->execute($approved->fresh(), $attempt > 1);
                $this->fail('Reversal unexpectedly succeeded without sufficient payee funds.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('insufficient active bahar', strtolower($e->getMessage()));
            }

            $fresh = $approved->fresh();
            $this->assertSame($attempt, (int) $fresh->attempts);
            $this->assertSame($attempt === PublicExecutionReversalService::MAX_ATTEMPTS ? 'dead_letter' : 'failed', $fresh->status);
            $this->assertTrue((bool) ($fresh->metadata['operator_attention_required'] ?? false));
            $this->assertSame($transactionsBefore, Transaction::count());
        }

        $recovered = $service->recoverDeadLetter($approved->fresh());
        $payeeAccount->refresh();
        $payeeAccount->balance_active = 6;
        $payeeAccount->balance = 6 + (int) ($payeeAccount->balance_faded ?? 0) + (int) ($payeeAccount->committed_dim ?? 0);
        $payeeAccount->save();

        $executed = $service->execute($recovered, true);
        $this->assertSame('executed', $executed->status);
        $this->assertSame(9, (int) $executionAccount->fresh()->balance_active);
        $this->assertSame(1, (int) $payeeAccount->fresh()->balance_active);
        $this->assertFalse((bool) ($executed->metadata['operator_attention_required'] ?? true));
        $this->assertSame($transactionsBefore + 1, Transaction::count());
    }

    private function executedPayment(int $capitalGol, int $paymentGol): array
    {
        $group = Group::create(['name' => 'Reversal test group', 'group_type' => 'public', 'location_level' => 'neighborhood']);
        $manager = User::factory()->create();
        $inspector = User::factory()->create();
        $member = User::factory()->create();
        $contractor = User::factory()->create();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $manager->id, 'role' => 3, 'status' => 1]);
        GroupUser::create(['group_id' => $group->id, 'user_id' => $inspector->id, 'role' => 2, 'status' => 1]);
        GroupUser::create(['group_id' => $group->id, 'user_id' => $member->id, 'role' => 1, 'status' => 1]);

        $proposal = Proposal::create([
            'group_id' => $group->id, 'created_by' => $member->id, 'type' => 'public_project',
            'title' => 'Reversal test project', 'status' => 'approved',
        ]);
        $poll = Poll::create(['group_id' => $group->id, 'created_by' => $manager->id, 'question' => 'Approve?', 'is_active' => false]);
        $snapshot = app(EligibilitySnapshotService::class)->capture($group, $poll, $manager);
        $resolution = Resolution::create([
            'proposal_id' => $proposal->id, 'group_id' => $group->id, 'poll_id' => $poll->id,
            'eligibility_snapshot_id' => $snapshot->id, 'adopted_by' => $manager->id,
            'type' => 'public_project', 'status' => 'adopted', 'effect_status' => 'pending_bridge',
            'eligible_voter_count' => $snapshot->eligible_count, 'votes_cast' => $snapshot->eligible_count,
            'votes_for' => $snapshot->eligible_count,
            'financial_effect' => ['action' => ResolutionEconomicBridge::PUBLIC_PROJECT_APPROVED, 'requested_capital_gol' => $capitalGol],
            'adopted_at' => now(), 'effective_at' => now(),
        ]);

        $contributions = app(PublicContributionService::class);
        $plan = $contributions->createPlan(app(ResolutionEconomicBridge::class)->enqueue($resolution));
        $memberIds = $snapshot->chunks()->get()->flatMap(fn ($chunk) => $chunk->member_ids ?? [])->map(fn ($id) => (int) $id)->values();
        foreach ($memberIds as $userId) {
            $user = User::findOrFail($userId);
            $account = app(AccountService::class)->createMainAccountForUser($userId);
            $account->balance_faded = 100;
            $account->committed_dim = 0;
            $account->balance_active = 0;
            $account->balance = 100;
            $account->save();
            $contributions->commitDim($contributions->obligationForUser($plan->fresh(), $user), $user);
        }

        $authorization = app(PublicExecutionAuthorizationService::class)->authorize($plan->fresh(), $manager);
        app(GovernanceExecutionOutboxConsumer::class)->consume(app(PublicExecutionBridge::class)->enqueue($authorization));

        $payeeAccount = app(AccountService::class)->createMainAccountForUser($contractor->id);
        $instruction = app(PublicExecutionPaymentInstructionService::class)->create(
            $plan->fresh(), $payeeAccount, $paymentGol, 'Original contractor payment', $manager,
            'reversal-original-payment:' . $plan->id
        );
        $approvedPayment = app(PublicExecutionPaymentApprovalService::class)->approve($instruction, $inspector);
        $payment = app(PublicExecutionPaymentService::class)->execute($approvedPayment);
        $executionAccount = Account::findOrFail((int) $payment->execution_account_id);

        return [$payment->fresh(), $manager, $inspector, $executionAccount, $payeeAccount->fresh()];
    }
}
