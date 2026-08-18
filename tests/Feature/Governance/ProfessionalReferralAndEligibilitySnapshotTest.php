<?php

namespace Tests\Feature\Governance;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Poll;
use App\Models\User;
use App\Modules\Governance\Models\EligibilitySnapshot;
use App\Modules\Governance\Services\ProfessionalReferralService;
use App\Modules\Governance\Services\ProposalLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfessionalReferralAndEligibilitySnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_required_professional_referral_must_complete_before_vote_readiness(): void
    {
        [$source, $author, $sourceManager] = $this->assembly('مجمع محلی');
        [$professional, , $professionalManager] = $this->assembly('مجمع تخصصی کشاورزی');

        $lifecycle = app(ProposalLifecycleService::class);
        $referrals = app(ProfessionalReferralService::class);

        $proposal = $lifecycle->submitForDiscussion(
            $lifecycle->create($source, $author, ['title' => 'سامانه آبیاری عمومی', 'support_threshold' => 1]),
            $author
        );
        $lifecycle->support($proposal, $author);
        $agenda = $lifecycle->placeOnAgenda($proposal->fresh(), $sourceManager, true, 'نیازمند ارزیابی تخصصی آب و کشاورزی');

        try {
            $lifecycle->markAssessable($proposal->fresh(), $sourceManager);
            $this->fail('Proposal became assessable before required referral completed.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('professional referral', strtolower($e->getMessage()));
        }

        $referral = $referrals->refer($agenda, $professional, $sourceManager, 'بررسی فنی و برآورد سرمایه');
        $referral = $referrals->accept($referral, $professionalManager);
        $referral = $referrals->complete($referral, $professionalManager, [
            'technical_feasibility' => 'acceptable',
            'capital_range_gol' => [90000, 110000],
            'risk_level' => 'medium',
        ], 'طرح با اصلاحات اجرایی قابل بررسی است.');

        $proposal = $lifecycle->markAssessable($proposal->fresh(), $sourceManager);

        $this->assertSame('completed', $referral->status);
        $this->assertSame('ready_for_vote', $proposal->status);
        $this->assertSame('ready_for_vote', $agenda->fresh()->status);
    }

    public function test_only_target_assembly_manager_or_inspector_can_accept_referral(): void
    {
        [$source, $author, $sourceManager] = $this->assembly('مجمع مبدأ');
        [$target, $targetMember] = $this->assembly('مجمع مقصد');
        $lifecycle = app(ProposalLifecycleService::class);
        $referrals = app(ProfessionalReferralService::class);

        $proposal = $lifecycle->submitForDiscussion(
            $lifecycle->create($source, $author, ['title' => 'پیشنهاد', 'support_threshold' => 1]),
            $author
        );
        $lifecycle->support($proposal, $author);
        $agenda = $lifecycle->placeOnAgenda($proposal->fresh(), $sourceManager, true);
        $referral = $referrals->refer($agenda, $target, $sourceManager);

        $this->expectException(\RuntimeException::class);
        $referrals->accept($referral, $targetMember);
    }

    public function test_vote_eligibility_snapshot_remains_immutable_after_membership_changes(): void
    {
        [$group, $author, $manager] = $this->assembly('مجمع رأی');
        $member = User::factory()->create();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $member->id, 'role' => 1, 'status' => 1]);

        $lifecycle = app(ProposalLifecycleService::class);
        $proposal = $lifecycle->submitForDiscussion(
            $lifecycle->create($group, $author, ['title' => 'مصوبه عمومی', 'support_threshold' => 1]),
            $author
        );
        $lifecycle->support($proposal, $author);
        $lifecycle->placeOnAgenda($proposal->fresh(), $manager, false);
        $proposal = $lifecycle->markAssessable($proposal->fresh(), $manager);

        $poll = Poll::create([
            'group_id' => $group->id,
            'created_by' => $manager->id,
            'question' => 'تصویب شود؟',
            'is_active' => false,
        ]);

        $expectedEligible = GroupUser::query()
            ->join('users', 'users.id', '=', 'group_user.user_id')
            ->where('group_user.group_id', $group->id)
            ->where('group_user.status', 1)
            ->whereNull('group_user.deleted_at')
            ->where('users.is_system', false)
            ->count();

        $proposal = $lifecycle->openVote($proposal, $poll, $manager);

        $snapshot = EligibilitySnapshot::findOrFail((int) $proposal->metadata['eligibility_snapshot_id']);
        $this->assertSame($expectedEligible, (int) $snapshot->eligible_count);
        $fingerprint = $snapshot->membership_fingerprint;

        GroupUser::where('group_id', $group->id)->where('user_id', $member->id)->update(['status' => 0]);
        $lateMember = User::factory()->create();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $lateMember->id, 'role' => 1, 'status' => 1]);

        $currentEligibleAfterMembershipChange = GroupUser::query()
            ->join('users', 'users.id', '=', 'group_user.user_id')
            ->where('group_user.group_id', $group->id)
            ->where('group_user.status', 1)
            ->whereNull('group_user.deleted_at')
            ->where('users.is_system', false)
            ->count();
        $this->assertSame($expectedEligible, $currentEligibleAfterMembershipChange, 'Fixture swaps one voter for another while keeping cohort size equal.');

        $resolution = $lifecycle->recordDecision($proposal->fresh(), $poll, $manager, [
            'votes_cast' => min(3, $expectedEligible),
            'votes_for' => min(2, $expectedEligible),
            'votes_against' => $expectedEligible >= 3 ? 1 : 0,
            'votes_abstain' => 0,
            'quorum_required_percent' => 50,
            'approval_required_percent' => 50,
        ]);

        $this->assertSame($expectedEligible, (int) $resolution->eligible_voter_count);
        $this->assertSame($snapshot->id, (int) $resolution->eligibility_snapshot_id);
        $this->assertSame($fingerprint, $resolution->metadata['eligibility_fingerprint']);
        $this->assertSame($fingerprint, $snapshot->fresh()->membership_fingerprint);
    }

    private function assembly(string $name): array
    {
        $group = Group::create([
            'name' => $name,
            'group_type' => 'public',
            'location_level' => 'neighborhood',
        ]);

        $member = User::factory()->create();
        $manager = User::factory()->create();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $member->id, 'role' => 1, 'status' => 1]);
        GroupUser::create(['group_id' => $group->id, 'user_id' => $manager->id, 'role' => 3, 'status' => 1]);

        return [$group, $member, $manager];
    }
}
