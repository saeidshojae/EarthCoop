<?php

namespace Tests\Feature\Governance;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Poll;
use App\Models\User;
use App\Modules\Governance\Models\Proposal;
use App\Modules\Governance\Models\PublicContributionObligation;
use App\Modules\Governance\Models\Resolution;
use App\Modules\Governance\Services\EligibilitySnapshotService;
use App\Modules\Governance\Services\PublicContributionService;
use App\Modules\Governance\Services\ResolutionEconomicBridge;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\NajmBahar\Models\Transaction as NajmTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicContributionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_contribution_plan_is_lazy_exact_and_never_auto_debits_members(): void
    {
        [$resolution, $snapshot] = $this->adoptedPublicProjectResolution(11);
        $bridge = app(ResolutionEconomicBridge::class);
        $service = app(PublicContributionService::class);
        $projectsBefore = Project::count();
        $transactionsBefore = NajmTransaction::count();

        $action = $bridge->enqueue($resolution);
        $plan = $service->createPlan($action);
        $samePlan = $service->createPlan($action->fresh());

        $this->assertSame($plan->id, $samePlan->id);
        $this->assertSame((int) $snapshot->eligible_count, (int) $plan->eligible_count);
        $this->assertSame(11, (int) $plan->total_required_gol);
        $this->assertSame(intdiv(11, (int) $snapshot->eligible_count), (int) $plan->base_amount_gol);
        $this->assertSame(11 % (int) $snapshot->eligible_count, (int) $plan->remainder_gol);
        $this->assertSame(0, PublicContributionObligation::where('plan_id', $plan->id)->count(), 'Plan creation must not eagerly create one debt row per member.');
        $this->assertSame('completed', $action->fresh()->status);
        $this->assertSame('bridged', $resolution->fresh()->effect_status);
        $this->assertSame($projectsBefore, Project::count());
        $this->assertSame($transactionsBefore, NajmTransaction::count(), 'Creating a contribution plan must never debit money automatically.');

        $memberIds = $snapshot->chunks()->get()->flatMap(fn ($chunk) => $chunk->member_ids ?? [])->map(fn ($id) => (int) $id)->values();
        $obligations = $memberIds->map(function (int $userId) use ($plan, $service) {
            return $service->obligationForUser($plan, User::findOrFail($userId));
        });

        $this->assertSame(11, (int) $obligations->sum('amount_gol'));
        $this->assertSame($memberIds->count(), PublicContributionObligation::where('plan_id', $plan->id)->count());
        $this->assertTrue($obligations->every(fn ($obligation) => $obligation->status === 'pending' && (int) $obligation->paid_gol === 0));
        $this->assertSame($transactionsBefore, NajmTransaction::count(), 'Materializing obligations must still not move money.');

        $firstUser = User::findOrFail((int) $memberIds->first());
        $firstAgain = $service->obligationForUser($plan->fresh(), $firstUser);
        $this->assertSame($obligations->first()->id, $firstAgain->id, 'Per-user obligation materialization must be idempotent.');
    }

    public function test_user_outside_frozen_snapshot_cannot_receive_public_obligation(): void
    {
        [$resolution] = $this->adoptedPublicProjectResolution(100);
        $action = app(ResolutionEconomicBridge::class)->enqueue($resolution);
        $plan = app(PublicContributionService::class)->createPlan($action);
        $outsider = User::factory()->create();

        $this->expectException(\RuntimeException::class);
        app(PublicContributionService::class)->obligationForUser($plan, $outsider);
    }

    public function test_non_public_project_action_cannot_create_public_contribution_plan(): void
    {
        [$resolution] = $this->adoptedPublicProjectResolution(100, ResolutionEconomicBridge::PUBLIC_EXPENDITURE_APPROVED);
        $action = app(ResolutionEconomicBridge::class)->enqueue($resolution);

        $this->expectException(\RuntimeException::class);
        app(PublicContributionService::class)->createPlan($action);
    }

    private function adoptedPublicProjectResolution(int $capitalGol, string $actionType = ResolutionEconomicBridge::PUBLIC_PROJECT_APPROVED): array
    {
        $group = Group::create([
            'name' => 'مجمع تأمین مالی عمومی',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
        ]);

        $manager = User::factory()->create();
        $members = User::factory()->count(3)->create();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $manager->id, 'role' => 3, 'status' => 1]);
        foreach ($members as $member) {
            GroupUser::create(['group_id' => $group->id, 'user_id' => $member->id, 'role' => 1, 'status' => 1]);
        }

        $proposal = Proposal::create([
            'group_id' => $group->id,
            'created_by' => $members->first()->id,
            'type' => 'public_project',
            'title' => 'پروژه عمومی محله',
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
                'action' => $actionType,
                'requested_capital_gol' => $capitalGol,
            ],
            'metadata' => [
                'eligibility_fingerprint' => $snapshot->membership_fingerprint,
                'economic_effect_executed' => false,
            ],
            'adopted_at' => now(),
            'effective_at' => now(),
        ]);

        return [$resolution, $snapshot, $group, $manager, $members];
    }
}
