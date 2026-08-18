<?php

namespace Tests\Feature\Governance;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Poll;
use App\Models\User;
use App\Modules\Governance\Models\EconomicAction;
use App\Modules\Governance\Models\Proposal;
use App\Modules\Governance\Models\Resolution;
use App\Modules\Governance\Services\EligibilitySnapshotService;
use App\Modules\Governance\Services\ResolutionEconomicBridge;
use App\Modules\NajmBahar\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolutionEconomicBridgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_adopted_resolution_is_enqueued_idempotently_without_direct_economic_side_effect(): void
    {
        [$resolution] = $this->economicResolution(ResolutionEconomicBridge::PUBLIC_PROJECT_APPROVED);
        $bridge = app(ResolutionEconomicBridge::class);
        $projectsBefore = Project::count();

        $first = $bridge->enqueue($resolution);
        $second = $bridge->enqueue($resolution->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, EconomicAction::where('resolution_id', $resolution->id)->count());
        $this->assertSame('pending', $first->status);
        $this->assertSame(ResolutionEconomicBridge::PUBLIC_PROJECT_APPROVED, $first->action_type);
        $this->assertSame('queued', $resolution->fresh()->effect_status);
        $this->assertFalse((bool) ($resolution->fresh()->metadata['economic_effect_executed'] ?? true));
        $this->assertSame($projectsBefore, Project::count(), 'Economic bridge must enqueue only; project creation belongs to a downstream consumer.');
    }

    public function test_rejected_resolution_cannot_enter_economic_bridge(): void
    {
        [$resolution] = $this->economicResolution(ResolutionEconomicBridge::PUBLIC_EXPENDITURE_APPROVED, 'rejected');

        $this->expectException(\RuntimeException::class);
        app(ResolutionEconomicBridge::class)->enqueue($resolution);
    }

    public function test_unsupported_financial_effect_is_rejected_without_queueing_action(): void
    {
        [$resolution] = $this->economicResolution('ARBITRARY_DIRECT_DEBIT');

        try {
            app(ResolutionEconomicBridge::class)->enqueue($resolution);
            $this->fail('Unsupported economic action was queued.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Unsupported', $e->getMessage());
        }

        $this->assertSame(0, EconomicAction::where('resolution_id', $resolution->id)->count());
        $this->assertSame('pending_bridge', $resolution->fresh()->effect_status);
    }

    private function economicResolution(string $action, string $status = 'adopted'): array
    {
        $group = Group::create([
            'name' => 'مجمع پل اقتصادی',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
        ]);
        $member = User::factory()->create();
        $manager = User::factory()->create();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $member->id, 'role' => 1, 'status' => 1]);
        GroupUser::create(['group_id' => $group->id, 'user_id' => $manager->id, 'role' => 3, 'status' => 1]);

        $proposal = Proposal::create([
            'group_id' => $group->id,
            'created_by' => $member->id,
            'type' => 'public_project',
            'title' => 'طرح اقتصادی آزمایشی',
            'status' => $status === 'adopted' ? 'approved' : 'rejected',
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
            'status' => $status,
            'effect_status' => 'pending_bridge',
            'eligible_voter_count' => $snapshot->eligible_count,
            'votes_cast' => $snapshot->eligible_count,
            'votes_for' => $status === 'adopted' ? $snapshot->eligible_count : 0,
            'votes_against' => $status === 'adopted' ? 0 : $snapshot->eligible_count,
            'financial_effect' => [
                'action' => $action,
                'requested_capital_gol' => 250_000,
            ],
            'metadata' => [
                'eligibility_fingerprint' => $snapshot->membership_fingerprint,
                'economic_effect_executed' => false,
            ],
            'adopted_at' => $status === 'adopted' ? now() : null,
            'effective_at' => $status === 'adopted' ? now() : null,
        ]);

        return [$resolution, $snapshot, $group];
    }
}
