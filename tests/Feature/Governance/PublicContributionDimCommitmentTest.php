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
use App\Modules\Governance\Services\ResolutionEconomicBridge;
use App\Modules\NajmBahar\Models\LedgerEntry;
use App\Modules\NajmBahar\Models\Transaction;
use App\Modules\NajmBahar\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicContributionDimCommitmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_idempotently_commit_and_release_dim_without_activation_or_money_creation(): void
    {
        [$resolution, $member] = $this->resolutionAndMember(10);
        $service = app(PublicContributionService::class);
        $action = app(ResolutionEconomicBridge::class)->enqueue($resolution);
        $plan = $service->createPlan($action);
        $obligation = $service->obligationForUser($plan, $member);

        $account = app(AccountService::class)->createMainAccountForUser($member->id);
        $account->balance_active = 7;
        $account->balance_faded = 20;
        $account->committed_dim = 0;
        $account->balance = 27;
        $account->save();

        $transactionsBefore = Transaction::count();
        $committed = $service->commitDim($obligation, $member);
        $sameCommitment = $service->commitDim($committed->fresh(), $member);
        $account = $account->fresh();

        $this->assertSame($committed->id, $sameCommitment->id);
        $this->assertSame('committed', $committed->status);
        $this->assertSame((int) $obligation->amount_gol, (int) $committed->committed_gol);
        $this->assertSame(7, (int) $account->balance_active, 'Commitment must not activate Dim.');
        $this->assertSame(20 - (int) $obligation->amount_gol, (int) $account->balance_faded);
        $this->assertSame((int) $obligation->amount_gol, (int) $account->committed_dim);
        $this->assertSame(27, (int) $account->balance, 'Commitment must preserve total monetary ownership.');
        $this->assertSame($transactionsBefore + 1, Transaction::count(), 'Retrying commitment must be idempotent.');

        $commitTransaction = Transaction::latest('id')->firstOrFail();
        $this->assertSame('DIM_COMMITTED', $commitTransaction->metadata['event'] ?? null);
        $entries = LedgerEntry::where('transaction_id', $commitTransaction->id)->get();
        $this->assertCount(2, $entries);
        $this->assertSame(0, (int) $entries->sum('amount'), 'Dim commitment ledger entries must conserve money.');

        $released = $service->releaseDim($committed->fresh(), $member);
        $sameRelease = $service->releaseDim($released->fresh(), $member);
        $account = $account->fresh();

        $this->assertSame('pending', $sameRelease->status);
        $this->assertSame(0, (int) $sameRelease->committed_gol);
        $this->assertSame(20, (int) $account->balance_faded);
        $this->assertSame(0, (int) $account->committed_dim);
        $this->assertSame(27, (int) $account->balance);
        $this->assertSame($transactionsBefore + 2, Transaction::count(), 'Retrying release must be idempotent.');
    }

    public function test_insufficient_dim_rejects_commitment_atomically(): void
    {
        [$resolution, $member] = $this->resolutionAndMember(100);
        $service = app(PublicContributionService::class);
        $plan = $service->createPlan(app(ResolutionEconomicBridge::class)->enqueue($resolution));
        $obligation = $service->obligationForUser($plan, $member);

        $account = app(AccountService::class)->createMainAccountForUser($member->id);
        $account->balance_faded = 1;
        $account->committed_dim = 0;
        $account->balance = 1;
        $account->save();
        $transactionsBefore = Transaction::count();

        try {
            $service->commitDim($obligation, $member);
            $this->fail('Commitment unexpectedly succeeded with insufficient Dim.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Insufficient available Dim', $e->getMessage());
        }

        $account = $account->fresh();
        $this->assertSame(1, (int) $account->balance_faded);
        $this->assertSame(0, (int) $account->committed_dim);
        $this->assertSame(1, (int) $account->balance);
        $this->assertSame('pending', $obligation->fresh()->status);
        $this->assertSame(0, (int) $obligation->fresh()->committed_gol);
        $this->assertSame($transactionsBefore, Transaction::count());
    }

    private function resolutionAndMember(int $capitalGol): array
    {
        $group = Group::create([
            'name' => 'مجمع تعهد دیم',
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
            'title' => 'پروژه عمومی تعهد دیم',
            'status' => 'approved',
        ]);
        $poll = Poll::create([
            'group_id' => $group->id,
            'created_by' => $manager->id,
            'question' => 'تصویب شود؟',
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

        return [$resolution, $member];
    }
}
