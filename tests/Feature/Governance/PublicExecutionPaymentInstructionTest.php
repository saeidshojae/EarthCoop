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
use App\Modules\Governance\Services\ResolutionEconomicBridge;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\GovernanceExecutionOutboxConsumer;
use App\Modules\NajmBahar\Services\PublicExecutionPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicExecutionPaymentInstructionTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_payment_requires_distinct_second_approval_and_executes_once_under_duplicate_worker_attempts(): void
    {
        [$plan, $manager, $inspector, $contractor] = $this->executedPlan(10);
        $payeeAccount = app(AccountService::class)->createMainAccountForUser($contractor->id);
        $transactionsBeforeInstruction = Transaction::count();

        $instructionService = app(PublicExecutionPaymentInstructionService::class);
        $instruction = $instructionService->create(
            $plan->fresh(),
            $payeeAccount,
            6,
            'مرحله نخست قرارداد اجرای پروژه عمومی',
            $manager,
            'public-payment-instruction:plan:' . $plan->id . ':contractor:' . $contractor->id . ':1',
            ['contract_reference' => 'CONTRACT-001']
        );
        $same = $instructionService->create(
            $plan->fresh(),
            $payeeAccount,
            6,
            'مرحله نخست قرارداد اجرای پروژه عمومی',
            $manager,
            $instruction->idempotency_key,
            ['contract_reference' => 'CONTRACT-001']
        );

        $executionAccountId = (int) ($plan->fresh()->metadata['execution_account_id'] ?? 0);
        $executionAccount = Account::findOrFail($executionAccountId);
        $this->assertSame($instruction->id, $same->id);
        $this->assertSame('pending_approval', $instruction->status);
        $this->assertSame($transactionsBeforeInstruction, Transaction::count(), 'Creating a payment instruction must never move money.');

        $paymentService = app(PublicExecutionPaymentService::class);
        try {
            $paymentService->execute($instruction);
            $this->fail('Payment executed without the required second approval.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('second approval', strtolower($e->getMessage()));
        }

        $approvalService = app(PublicExecutionPaymentApprovalService::class);
        try {
            $approvalService->approve($instruction->fresh(), $manager);
            $this->fail('Instruction creator approved their own payment.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('cannot approve', strtolower($e->getMessage()));
        }

        $approved = $approvalService->approve($instruction->fresh(), $inspector);
        $this->assertSame('approved', $approved->status);
        $this->assertSame((int) $inspector->id, (int) $approved->approved_by);
        $this->assertNotNull($approved->approved_at);

        $staleWorkerCopy = $instruction->replicate();
        $staleWorkerCopy->id = $instruction->id;
        $staleWorkerCopy->exists = true;

        $executed = $paymentService->execute($approved);
        $this->assertSame('executed', $executed->status);
        $this->assertSame(4, (int) $executionAccount->fresh()->balance_active);
        $this->assertSame(6, (int) $payeeAccount->fresh()->balance_active);
        $this->assertSame($transactionsBeforeInstruction + 1, Transaction::count());

        $paymentService->execute($staleWorkerCopy);
        $this->assertSame(4, (int) $executionAccount->fresh()->balance_active);
        $this->assertSame(6, (int) $payeeAccount->fresh()->balance_active);
        $this->assertSame(
            $transactionsBeforeInstruction + 1,
            Transaction::count(),
            'A duplicate worker using a stale instruction instance must not move money twice.'
        );
    }

    public function test_unexecuted_payment_instruction_can_be_cancelled_and_releases_reserved_capacity(): void
    {
        [$plan, $manager, $inspector, $contractor] = $this->executedPlan(10);
        $payeeAccount = app(AccountService::class)->createMainAccountForUser($contractor->id);
        $instructionService = app(PublicExecutionPaymentInstructionService::class);
        $approvalService = app(PublicExecutionPaymentApprovalService::class);

        $cancelledCandidate = $instructionService->create(
            $plan->fresh(),
            $payeeAccount,
            7,
            'دستور قابل لغو',
            $manager,
            'public-payment-cancel-test:' . $plan->id,
            ['contract_reference' => 'CONTRACT-CANCEL']
        );
        $approved = $approvalService->approve($cancelledCandidate, $inspector);
        $transactionsBeforeCancellation = Transaction::count();

        $cancelled = $approvalService->cancel($approved, $manager, 'قرارداد پیش از پرداخت فسخ شد.');
        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame((int) $manager->id, (int) $cancelled->cancelled_by);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame($transactionsBeforeCancellation, Transaction::count());

        $replacement = $instructionService->create(
            $plan->fresh(),
            $payeeAccount,
            10,
            'دستور جایگزین پس از لغو',
            $manager,
            'public-payment-replacement-test:' . $plan->id,
            ['contract_reference' => 'CONTRACT-REPLACEMENT']
        );
        $this->assertSame('pending_approval', $replacement->status, 'Cancelled instructions must release reserved execution-account capacity.');

        try {
            app(PublicExecutionPaymentService::class)->execute($cancelled->fresh());
            $this->fail('Cancelled instruction was executed.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('second approval', strtolower($e->getMessage()));
        }
    }

    private function executedPlan(int $capitalGol): array
    {
        $group = Group::create([
            'name' => 'مجمع دستور پرداخت عمومی',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
        ]);
        $manager = User::factory()->create();
        $inspector = User::factory()->create();
        $member = User::factory()->create();
        $contractor = User::factory()->create();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $manager->id, 'role' => 3, 'status' => 1]);
        GroupUser::create(['group_id' => $group->id, 'user_id' => $inspector->id, 'role' => 2, 'status' => 1]);
        GroupUser::create(['group_id' => $group->id, 'user_id' => $member->id, 'role' => 1, 'status' => 1]);

        $proposal = Proposal::create([
            'group_id' => $group->id,
            'created_by' => $member->id,
            'type' => 'public_project',
            'title' => 'پروژه دارای پیمانکار صریح',
            'status' => 'approved',
        ]);
        $poll = Poll::create([
            'group_id' => $group->id,
            'created_by' => $manager->id,
            'question' => 'پروژه تصویب شود؟',
            'is_active' => false,
        ]);
        $snapshot = app(EligibilitySnapshotService::class)->capture($group, $poll, $manager);
        $resolution = Resolution::create([
            'proposal_id' => $proposal->id,
            'group_id' => $group->id,
            'poll_id' => $poll->id,
            'eligibility_snapshot_id' => $snapshot->id,
            'adopted_by' => $manager->id,
            'type' => 'public_project',
            'status' => 'adopted',
            'effect_status' => 'pending_bridge',
            'eligible_voter_count' => $snapshot->eligible_count,
            'votes_cast' => $snapshot->eligible_count,
            'votes_for' => $snapshot->eligible_count,
            'financial_effect' => [
                'action' => ResolutionEconomicBridge::PUBLIC_PROJECT_APPROVED,
                'requested_capital_gol' => $capitalGol,
            ],
            'adopted_at' => now(),
            'effective_at' => now(),
        ]);

        $contributions = app(PublicContributionService::class);
        $plan = $contributions->createPlan(app(ResolutionEconomicBridge::class)->enqueue($resolution));
        $memberIds = $snapshot->chunks()->get()
            ->flatMap(fn ($chunk) => $chunk->member_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->values();

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
        $action = app(PublicExecutionBridge::class)->enqueue($authorization);
        app(GovernanceExecutionOutboxConsumer::class)->consume($action);

        return [$plan->fresh(), $manager, $inspector, $contractor];
    }
}
