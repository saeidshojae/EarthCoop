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
use App\Modules\NajmBahar\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicContributionFundingCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_becomes_fully_committed_only_at_exact_approved_capital_without_activation(): void
    {
        [$resolution, $snapshot] = $this->resolution(11);
        $service = app(PublicContributionService::class);
        $plan = $service->createPlan(app(ResolutionEconomicBridge::class)->enqueue($resolution));

        $memberIds = $snapshot->chunks()->get()
            ->flatMap(fn ($chunk) => $chunk->member_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->values();

        $obligations = [];
        foreach ($memberIds as $userId) {
            $user = User::findOrFail($userId);
            $account = app(AccountService::class)->createMainAccountForUser($userId);
            $account->balance_active = 0;
            $account->balance_faded = 100;
            $account->committed_dim = 0;
            $account->balance = 100;
            $account->save();
            $obligations[] = [$service->obligationForUser($plan->fresh(), $user), $user, $account];
        }

        foreach ($obligations as $index => [$obligation, $user]) {
            $service->commitDim($obligation, $user);
            $plan = $plan->fresh();

            if ($index < count($obligations) - 1) {
                $this->assertSame('open', $plan->status);
                $this->assertNull($plan->fully_committed_at);
                $this->assertLessThan((int) $plan->total_required_gol, (int) $plan->committed_total_gol);
            }
        }

        $plan = $plan->fresh();
        $this->assertSame('fully_committed', $plan->status);
        $this->assertSame(11, (int) $plan->committed_total_gol);
        $this->assertNotNull($plan->fully_committed_at);

        foreach ($obligations as [, , $account]) {
            $account = $account->fresh();
            $this->assertSame(0, (int) $account->balance_active, 'Full commitment must not activate any Dim.');
            $this->assertSame(100, (int) $account->balance, 'Full commitment must preserve each member total balance.');
        }

        [$firstObligation, $firstUser] = $obligations[0];
        $service->releaseDim($firstObligation->fresh(), $firstUser);
        $plan = $plan->fresh();

        $this->assertSame('open', $plan->status);
        $this->assertLessThan(11, (int) $plan->committed_total_gol);
        $this->assertNull($plan->fully_committed_at);
    }

    private function resolution(int $capitalGol): array
    {
        $group = Group::create([
            'name' => 'مجمع تکمیل سرمایه عمومی',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
        ]);
        $manager = User::factory()->create();
        $members = User::factory()->count(2)->create();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $manager->id, 'role' => 3, 'status' => 1]);
        foreach ($members as $member) {
            GroupUser::create(['group_id' => $group->id, 'user_id' => $member->id, 'role' => 1, 'status' => 1]);
        }

        $proposal = Proposal::create([
            'group_id' => $group->id,
            'created_by' => $members->first()->id,
            'type' => 'public_project',
            'title' => 'پروژه عمومی تکمیل سرمایه',
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

        return [$resolution, $snapshot];
    }
}
