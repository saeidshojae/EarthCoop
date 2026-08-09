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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicExecutionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_execution_authorization_requires_full_commitment_and_never_moves_money(): void
    {
        [$resolution, $snapshot, $manager] = $this->resolution(10);
        $contributions = app(PublicContributionService::class);
        $authorizations = app(PublicExecutionAuthorizationService::class);
        $plan = $contributions->createPlan(app(ResolutionEconomicBridge::class)->enqueue($resolution));

        try {
            $authorizations->authorize($plan, $manager, ['contractor_verified' => true]);
            $this->fail('Execution was authorized before funding was fully committed.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('fully committed', strtolower($e->getMessage()));
        }

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

        $this->assertSame('fully_committed', $plan->fresh()->status);
        $transactionsBeforeAuthorization = Transaction::count();

        $authorization = $authorizations->authorize($plan->fresh(), $manager, [
            'contractor_verified' => true,
            'execution_budget_locked' => true,
        ]);
        $sameAuthorization = $authorizations->authorize($plan->fresh(), $manager);

        $this->assertSame($authorization->id, $sameAuthorization->id);
        $this->assertSame('authorized', $authorization->status);
        $this->assertSame('execution_authorized', $plan->fresh()->status);
        $this->assertTrue((bool) ($plan->fresh()->metadata['execution_authorized'] ?? false));
        $this->assertFalse((bool) ($plan->fresh()->metadata['monetary_execution_started'] ?? true));
        $this->assertSame($transactionsBeforeAuthorization, Transaction::count(), 'Governance authorization must not move or activate money.');

        $bridge = app(PublicExecutionBridge::class);
        $action = $bridge->enqueue($authorization);
        $sameAction = $bridge->enqueue($authorization->fresh());

        $this->assertSame($action->id, $sameAction->id, 'Execution outbox enqueue must be idempotent.');
        $this->assertSame(PublicExecutionBridge::PUBLIC_PROJECT_EXECUTION_AUTHORIZED, $action->action_type);
        $this->assertSame('pending', $action->status);
        $this->assertSame('execution_queued', $plan->fresh()->status);
        $this->assertFalse((bool) ($plan->fresh()->metadata['monetary_execution_started'] ?? true));
        $this->assertSame($transactionsBeforeAuthorization, Transaction::count(), 'Queuing execution must still not move or activate money.');
    }

    private function resolution(int $capitalGol): array
    {
        $group = Group::create([
            'name' => 'مجمع مجوز اجرای عمومی',
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
            'title' => 'پروژه عمومی دارای مجوز اجرا',
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
