<?php

namespace Tests\Feature\NajmBahar;

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
use App\Modules\NajmBahar\Services\ActiveBaharReservationService;
use App\Modules\NajmBahar\Services\GovernanceExecutionOutboxConsumer;
use App\Modules\NajmBahar\Services\PublicExecutionPaymentService;
use App\Modules\NajmBahar\Services\PublicExecutionReversalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicExecutionReservationProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_payment_cannot_spend_active_reserved_for_another_obligation(): void
    {
        [$plan, $manager, $inspector, $contractor] = $this->executedPlan(10);
        $payee = app(AccountService::class)->createMainAccountForUser($contractor->id);
        $instruction = app(PublicExecutionPaymentInstructionService::class)->create(
            $plan->fresh(),
            $payee,
            6,
            'پرداختی که نباید از رزرو مستقل خرج کند',
            $manager,
            'public-payment-reservation-protection:' . $plan->id
        );
        $approved = app(PublicExecutionPaymentApprovalService::class)->approve($instruction, $inspector);
        $execution = Account::findOrFail((int) $approved->execution_account_id);

        app(ActiveBaharReservationService::class)->reserve(
            $execution->account_number,
            8,
            'independent-execution-reservation:' . $execution->id,
            'independent_obligation',
            1
        );

        $transactionsBefore = Transaction::count();
        try {
            app(PublicExecutionPaymentService::class)->execute($approved);
            $this->fail('Public payment spent Active Bahar backing an independent reservation.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('insufficient active bahar', strtolower($e->getMessage()));
        }

        $this->assertSame(10, (int) $execution->fresh()->balance_active);
        $this->assertSame(2, app(ActiveBaharReservationService::class)->availableActive($execution->fresh()));
        $this->assertSame(0, (int) $payee->fresh()->balance_active);
        $this->assertSame($transactionsBefore, Transaction::count());
    }

    public function test_public_reversal_cannot_spend_payee_active_reserved_for_another_obligation(): void
    {
        [$plan, $manager, $inspector, $contractor] = $this->executedPlan(10);
        $payee = app(AccountService::class)->createMainAccountForUser($contractor->id);
        $instruction = app(PublicExecutionPaymentInstructionService::class)->create(
            $plan->fresh(),
            $payee,
            6,
            'پرداخت مبنای آزمون reversal رزرو',
            $manager,
            'public-reversal-reservation-payment:' . $plan->id
        );
        $approved = app(PublicExecutionPaymentApprovalService::class)->approve($instruction, $inspector);
        $executed = app(PublicExecutionPaymentService::class)->execute($approved);
        $execution = Account::findOrFail((int) $executed->execution_account_id);

        app(ActiveBaharReservationService::class)->reserve(
            $payee->account_number,
            5,
            'independent-payee-reservation:' . $payee->id,
            'independent_obligation',
            1
        );

        $request = app(PublicExecutionReversalRequestService::class)->create(
            $executed->fresh(),
            3,
            'بازگشت وجهی که نباید رزرو مستقل پیمانکار را مصرف کند',
            $manager,
            'public-reversal-reservation-protection:' . $executed->id
        );
        $approvedRequest = app(PublicExecutionReversalRequestService::class)->approve($request, $inspector);
        $transactionsBefore = Transaction::count();

        try {
            app(PublicExecutionReversalService::class)->execute($approvedRequest);
            $this->fail('Public reversal spent Active Bahar backing an independent reservation.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('insufficient active bahar', strtolower($e->getMessage()));
        }

        $this->assertSame(6, (int) $payee->fresh()->balance_active);
        $this->assertSame(1, app(ActiveBaharReservationService::class)->availableActive($payee->fresh()));
        $this->assertSame(4, (int) $execution->fresh()->balance_active);
        $this->assertSame($transactionsBefore, Transaction::count());
    }

    private function executedPlan(int $capitalGol): array
    {
        $group = Group::create([
            'name' => 'مجمع حفاظت رزرو اجرای عمومی',
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
            'title' => 'پروژه آزمون حفاظت رزرو',
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
