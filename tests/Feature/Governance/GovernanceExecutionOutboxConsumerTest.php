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
use App\Modules\Governance\Services\ResolutionEconomicBridge;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Services\AccountService;
use App\Modules\NajmBahar\Services\GovernanceExecutionOutboxConsumer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GovernanceExecutionOutboxConsumerTest extends TestCase
{
    use RefreshDatabase;

    public function test_najm_bahar_consumes_execution_outbox_once_and_serializes_duplicate_worker_attempts(): void
    {
        [$resolution, $snapshot, $manager] = $this->resolution(10);
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

            $obligation = $contributions->obligationForUser($plan->fresh(), $user);
            $contributions->commitDim($obligation, $user);
        }

        $authorization = app(PublicExecutionAuthorizationService::class)->authorize(
            $plan->fresh(),
            $manager,
            ['contractor_verified' => true, 'execution_budget_locked' => true]
        );
        $action = app(PublicExecutionBridge::class)->enqueue($authorization);
        $transactionsBeforeConsumption = Transaction::count();

        $staleWorkerCopy = $action->replicate();
        $staleWorkerCopy->id = $action->id;
        $staleWorkerCopy->exists = true;

        $consumer = app(GovernanceExecutionOutboxConsumer::class);
        $completed = $consumer->consume($action);

        $executionAccount = app(AccountService::class)->ensureLegalEntityAccountForGroup($plan->group()->firstOrFail())->fresh();
        $this->assertSame('completed', $completed->status);
        $this->assertSame(10, (int) $completed->result['settled_total_gol']);
        $this->assertSame((int) $executionAccount->id, (int) $completed->result['execution_account_id']);
        $this->assertSame(10, (int) $executionAccount->balance_active);
        $this->assertSame(10, (int) $executionAccount->balance);

        $this->assertSame('executed', $plan->fresh()->status);
        $this->assertSame(0, (int) $plan->fresh()->committed_total_gol);
        $this->assertTrue((bool) ($plan->fresh()->metadata['monetary_execution_completed'] ?? false));
        $this->assertSame('consumed', $authorization->fresh()->status);
        $this->assertSame('executed', $resolution->fresh()->effect_status);
        $this->assertTrue((bool) ($resolution->fresh()->metadata['economic_effect_executed'] ?? false));

        foreach ($plan->obligations()->get() as $obligation) {
            $this->assertSame('paid', $obligation->status);
            $this->assertSame((int) $obligation->amount_gol, (int) $obligation->paid_gol);
            $this->assertSame(0, (int) $obligation->committed_gol);

            $memberAccount = app(AccountService::class)->getMainAccountForUser((int) $obligation->user_id)->fresh();
            $this->assertSame(0, (int) $memberAccount->committed_dim);
            $this->assertSame(0, (int) $memberAccount->balance_active);
            $this->assertSame(100 - (int) $obligation->amount_gol, (int) $memberAccount->balance_faded);
            $this->assertSame(100 - (int) $obligation->amount_gol, (int) $memberAccount->balance);
        }

        $expectedTransactions = $transactionsBeforeConsumption + $memberIds->count();
        $this->assertSame($expectedTransactions, Transaction::count());

        $consumer->consume($staleWorkerCopy);
        $this->assertSame(
            $expectedTransactions,
            Transaction::count(),
            'A duplicate worker using a stale outbox instance must observe the locked completed state and never settle twice.'
        );
        $this->assertSame(10, (int) $executionAccount->fresh()->balance_active);
    }

    private function resolution(int $capitalGol): array
    {
        $group = Group::create([
            'name' => 'مجمع مصرف Outbox اجرای عمومی',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
        ]);
        $manager = User::factory()->create();
        $member = User::factory()->create();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $manager->id, 'role' => 3, 'status' => 1]);
        GroupUser::create(['group_id' => $group->id, 'user_id' => $member->id, 'role' => 1, 'status' => 1]);

        $proposal = Proposal::create([
            'group_id' => $group->id,
            'created_by' => $member->id,
            'type' => 'public_project',
            'title' => 'پروژه عمومی برای مصرف Outbox',
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
            'metadata' => [
                'eligibility_fingerprint' => $snapshot->membership_fingerprint,
                'economic_effect_executed' => false,
            ],
            'adopted_at' => now(),
            'effective_at' => now(),
        ]);

        return [$resolution, $snapshot, $manager];
    }
}
