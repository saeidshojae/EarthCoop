<?php

namespace Tests\Feature\Home;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\User;
use App\Modules\NajmBahar\Services\AccountService;
use App\Services\Elections\CurrentElectionCenterService;
use App\Services\Home\HomeCivicDashboardService;
use App\Services\InvitationLifecycleService;
use App\Services\ProfileCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class HomeCivicDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_composes_real_user_state_for_journey_today_and_najm_bahar_priority(): void
    {
        [$user, $group] = $this->memberGroup();

        $actionable = $this->poll($group, $user, true, now()->addDay());
        $this->poll($group, $user, true, now()->subMinute());
        $this->poll($group, $user, false, now()->addDay());
        $voted = $this->poll($group, $user, true, now()->addDay());
        $option = $voted->options()->create(['text' => 'گزینه']);
        PollVote::create([
            'poll_id' => $voted->id,
            'user_id' => $user->id,
            'option_id' => $option->id,
        ]);

        $service = $this->service(
            residenceComplete: true,
            hasNajmBaharAccount: false,
            remainingInvitationSlots: 6,
            electionActionRequired: 2,
        );

        $dashboard = $service->forUser($user, [
            'public' => 9,
            'specialized' => 54,
            'exclusive' => 18,
        ], 3);

        $this->assertSame(81, $dashboard['journey']['groups']['total']);
        $this->assertSame('needs_attention', $dashboard['journey']['najm_bahar']['status']);
        $this->assertFalse($dashboard['journey']['najm_bahar']['active']);
        $this->assertSame(6, $dashboard['journey']['invitation']['remaining_slots']);
        $this->assertSame(2, $dashboard['today']['election_action_required']);
        $this->assertSame(1, $dashboard['today']['poll_action_required']);
        $this->assertSame(3, $dashboard['today']['pending_location_groups']);
        $this->assertSame('najm-bahar.dashboard', $dashboard['next_action']['route']);
        $this->assertSame($actionable->id, Poll::query()->whereKey($actionable->id)->value('id'));
    }

    public function test_recommendation_priority_is_residence_then_najm_bahar_then_election_then_poll_then_invitation_then_civic_fallback(): void
    {
        [$user, $group] = $this->memberGroup();
        $this->poll($group, $user, true, now()->addDay());

        $this->assertSame('register.step3', $this->service(false, false, 5, 4)
            ->forUser($user, ['public' => 1, 'specialized' => 0, 'exclusive' => 0])['next_action']['route']);

        $this->assertSame('najm-bahar.dashboard', $this->service(true, false, 5, 4)
            ->forUser($user, ['public' => 1, 'specialized' => 0, 'exclusive' => 0])['next_action']['route']);

        $this->assertSame('history.election', $this->service(true, true, 5, 4)
            ->forUser($user, ['public' => 1, 'specialized' => 0, 'exclusive' => 0])['next_action']['route']);

        $this->assertSame('history.poll', $this->service(true, true, 5, 0)
            ->forUser($user, ['public' => 1, 'specialized' => 0, 'exclusive' => 0])['next_action']['route']);

        Poll::query()->update(['is_active' => false]);
        $this->assertSame('my-invation-code', $this->service(true, true, 5, 0)
            ->forUser($user, ['public' => 1, 'specialized' => 0, 'exclusive' => 0])['next_action']['route']);

        $this->assertSame('location-governance.me', $this->service(true, true, 0, 0)
            ->forUser($user, ['public' => 1, 'specialized' => 0, 'exclusive' => 0])['next_action']['route']);
    }

    private function service(
        bool $residenceComplete,
        bool $hasNajmBaharAccount,
        int $remainingInvitationSlots,
        int $electionActionRequired,
    ): HomeCivicDashboardService {
        $profile = Mockery::mock(ProfileCompletionService::class);
        $profile->shouldReceive('hasRequiredResidence')->andReturn($residenceComplete);

        $accounts = Mockery::mock(AccountService::class);
        $accounts->shouldReceive('hasMainAccount')->andReturn($hasNajmBaharAccount);

        $invitations = Mockery::mock(InvitationLifecycleService::class);
        $invitations->shouldReceive('remainingSlots')->andReturn($remainingInvitationSlots);

        $elections = Mockery::mock(CurrentElectionCenterService::class);
        $elections->shouldReceive('forUser')->andReturn([
            'systemic' => collect(),
            'internal' => collect(),
            'summary' => [
                'action_required' => $electionActionRequired,
                'related' => 0,
                'total' => $electionActionRequired,
            ],
        ]);

        return new HomeCivicDashboardService($profile, $accounts, $invitations, $elections);
    }

    private function memberGroup(): array
    {
        $user = User::factory()->create(['is_system' => false]);
        $group = Group::create([
            'name' => 'Home civic dashboard',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
            'is_open' => true,
        ]);

        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 1,
            'status' => 1,
        ]);

        return [$user, $group];
    }

    private function poll(Group $group, User $creator, bool $active, $expiresAt): Poll
    {
        return Poll::create([
            'group_id' => $group->id,
            'created_by' => $creator->id,
            'question' => 'Home action poll ' . uniqid('', true),
            'main_type' => 1,
            'is_active' => $active,
            'expires_at' => $expiresAt,
        ]);
    }
}
