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
use App\Modules\Governance\Services\PublicExecutionPaymentInstructionService;
use App\Modules\Governance\Services\ResolutionEconomicBridge;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\GovernanceExecutionOutboxConsumer;
use App\Modules\NajmBahar\Services\PublicExecutionPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicExecutionPaymentInstructionTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_payment_instruction_records_payee_then_najm_bahar_executes_it_once(): void
    {
        $group = Group::create([
            'name' => 'مجمع دستور پرداخت عمومی',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
        ]);
        $manager = User::factory()->create();
        $member = User::factory()->create();
        $contractor = User::factory()->create();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $manager->id, 'role' => 3, 'status' => 1]);
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
                'requested_capital_gol' => 10,
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
        $executionAccount = \App\Modules\NajmBahar\Models\Account::findOrFail($executionAccountId);
        $this->assertSame($instruction->id, $same->id);
        $this->assertSame('pending', $instruction->status);
        $this->assertSame($executionAccountId, (int) $instruction->execution_account_id);
        $this->assertSame((int) $payeeAccount->id, (int) $instruction->payee_account_id);
        $this->assertSame(6, (int) $instruction->amount_gol);
        $this->assertTrue((bool) ($instruction->metadata['governance_instruction_only'] ?? false));
        $this->assertSame($transactionsBeforeInstruction, Transaction::count(), 'Creating a payment instruction must never move money.');
        $this->assertSame(0, (int) $payeeAccount->fresh()->balance_active);
        $this->assertSame(10, (int) $executionAccount->fresh()->balance_active);

        $paymentService = app(PublicExecutionPaymentService::class);
        $executed = $paymentService->execute($instruction);

        $this->assertSame('executed', $executed->status);
        $this->assertNotNull($executed->executed_at);
        $this->assertSame(4, (int) $executionAccount->fresh()->balance_active);
        $this->assertSame(6, (int) $payeeAccount->fresh()->balance_active);
        $this->assertSame($transactionsBeforeInstruction + 1, Transaction::count());

        $paymentService->execute($executed->fresh());
        $this->assertSame(4, (int) $executionAccount->fresh()->balance_active);
        $this->assertSame(6, (int) $payeeAccount->fresh()->balance_active);
        $this->assertSame($transactionsBeforeInstruction + 1, Transaction::count(), 'Retrying an executed payment instruction must not move money twice.');
    }
}
