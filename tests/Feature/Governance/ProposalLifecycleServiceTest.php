<?php

namespace Tests\Feature\Governance;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Poll;
use App\Models\User;
use App\Modules\Governance\Models\Resolution;
use App\Modules\Governance\Services\ProposalLifecycleService;
use App\Modules\NajmBahar\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProposalLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_supported_proposal_can_reach_resolution_without_direct_economic_side_effect(): void
    {
        [$group, $author, $supporter, $manager] = $this->assembly();
        $service = app(ProposalLifecycleService::class);

        $proposal = $service->create($group, $author, [
            'type' => ProposalLifecycleService::TYPE_PUBLIC_PROJECT,
            'title' => 'ساخت مرکز عمومی محله',
            'support_threshold' => 2,
        ]);

        $proposal = $service->submitForDiscussion($proposal, $author);
        $service->support($proposal, $author, 'reaction', 'message:10');
        $service->support($proposal->fresh(), $supporter, 'comment_reference', 'comment:20');
        $proposal = $proposal->fresh();

        $this->assertSame('supported', $proposal->status);
        $this->assertSame(2, (int) $proposal->support_count);

        $agenda = $service->placeOnAgenda($proposal, $manager, true, 'نیازمند بررسی مجمع تخصصی مرتبط');
        $this->assertSame('referral_pending', $agenda->status);

        $proposal = $service->markAssessable($proposal->fresh(), $manager, ['valuation_ready' => true]);
        $this->assertSame('ready_for_vote', $proposal->status);

        $poll = Poll::create([
            'group_id' => $group->id,
            'created_by' => $manager->id,
            'question' => 'آیا طرح تصویب شود؟',
            'is_active' => false,
        ]);

        $proposal = $service->openVote($proposal, $poll, $manager);
        $projectsBefore = Project::count();

        $resolution = $service->recordDecision($proposal, $poll, $manager, [
            'eligible_voter_count' => 3,
            'votes_cast' => 3,
            'votes_for' => 2,
            'votes_against' => 1,
            'votes_abstain' => 0,
            'quorum_required_percent' => 50,
            'approval_required_percent' => 50,
        ], [
            'action' => 'PUBLIC_PROJECT_APPROVED',
            'requested_capital_gol' => 100_000,
        ]);

        $this->assertSame('adopted', $resolution->status);
        $this->assertSame('pending_bridge', $resolution->effect_status);
        $this->assertFalse((bool) ($resolution->metadata['economic_effect_executed'] ?? true));
        $this->assertSame('approved', $proposal->fresh()->status);
        $this->assertSame($projectsBefore, Project::count(), 'Governance resolution must not create an economic project directly.');
    }

    public function test_only_supported_proposals_can_be_placed_on_agenda(): void
    {
        [$group, $author, , $manager] = $this->assembly();
        $service = app(ProposalLifecycleService::class);
        $proposal = $service->create($group, $author, ['title' => 'پیشنهاد خام', 'support_threshold' => 2]);
        $proposal = $service->submitForDiscussion($proposal, $author);

        $this->expectException(\RuntimeException::class);
        $service->placeOnAgenda($proposal, $manager);
    }

    public function test_outsider_cannot_support_group_proposal(): void
    {
        [$group, $author] = $this->assembly();
        $outsider = User::factory()->create();
        $service = app(ProposalLifecycleService::class);
        $proposal = $service->submitForDiscussion(
            $service->create($group, $author, ['title' => 'پیشنهاد']),
            $author
        );

        $this->expectException(\RuntimeException::class);
        $service->support($proposal, $outsider);
    }

    public function test_only_manager_or_inspector_can_move_supported_proposal_to_agenda(): void
    {
        [$group, $author, $supporter] = $this->assembly();
        $service = app(ProposalLifecycleService::class);
        $proposal = $service->submitForDiscussion(
            $service->create($group, $author, ['title' => 'پیشنهاد', 'support_threshold' => 1]),
            $author
        );
        $service->support($proposal, $author);

        $this->expectException(\RuntimeException::class);
        $service->placeOnAgenda($proposal->fresh(), $supporter);
    }

    private function assembly(): array
    {
        $group = Group::create([
            'name' => 'مجمع آزمایشی',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
        ]);

        $author = User::factory()->create();
        $supporter = User::factory()->create();
        $manager = User::factory()->create();

        GroupUser::create(['group_id' => $group->id, 'user_id' => $author->id, 'role' => 1, 'status' => 1]);
        GroupUser::create(['group_id' => $group->id, 'user_id' => $supporter->id, 'role' => 1, 'status' => 1]);
        GroupUser::create(['group_id' => $group->id, 'user_id' => $manager->id, 'role' => 3, 'status' => 1]);

        return [$group, $author, $supporter, $manager];
    }
}
