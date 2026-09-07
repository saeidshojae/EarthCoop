<?php

namespace Tests\Feature\Elections;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Models\Election;
use App\Models\ElectionPolicyVersion;
use App\Models\ElectionResponsibilityContractVersion;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\GroupUser;
use App\Models\User;
use App\Models\Vote;
use App\Services\Elections\ElectionBallotService;
use App\Services\Elections\ElectionEligibilitySnapshotService;
use App\Services\Elections\ElectionResponsibilityContractVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionContinuousBallotCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_lifecycle_tick_stops_due_window_opens_successor_and_advances_stopped_cycle_to_offers(): void
    {
        $group = Group::create([
            'name' => 'Continuous E0 ballot group',
            'group_type' => 'public',
            'location_level' => 'global',
            'address_id' => null,
        ]);
        $setting = GroupSetting::create([
            'level' => 'global',
            'manager_count' => 1,
            'inspector_count' => 0,
            'election_time' => 30,
            'max_for_election' => 1,
            'election_status' => 1,
            'second_election_time' => 6,
        ]);

        $voter = User::factory()->create(['is_system' => false]);
        $candidate = User::factory()->create(['is_system' => false]);
        foreach ([$voter, $candidate] as $user) {
            GroupUser::create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role' => 1,
                'status' => 1,
            ]);
        }

        $clauses = array_fill_keys(
            ElectionResponsibilityContractVersion::REQUIRED_CLAUSES,
            'متن کامل قرارداد آزمون انتخابات پیوسته',
        );
        $managerContract = app(ElectionResponsibilityContractVersionService::class)
            ->publish('manager', $clauses, $voter, 'continuous election manager contract');

        $policy = ElectionPolicyVersion::create([
            'group_setting_id' => $setting->id,
            'level_key' => 'global',
            'version' => 1,
            'election_status' => true,
            'manager_count' => 1,
            'inspector_count' => 0,
            'voting_duration_days' => 30,
            'start_threshold' => 1,
            'cycle_interval_months' => 6,
            'response_duration_days' => 7,
            'report_min_distinct_voters' => 10,
            'report_bucket_days' => 7,
            'meaningful_trend_min_net_change' => 3,
            'manager_contract_version_id' => $managerContract->id,
            'effective_at' => now()->subMonths(2),
            'change_reason' => 'continuous ballot test',
        ]);

        $old = Election::create([
            'group_id' => $group->id,
            'cycle_number' => 1,
            'policy_version_id' => $policy->id,
            'starts_at' => now()->subDays(31),
            'ends_at' => now()->subMinute(),
            'is_closed' => false,
            'lifecycle_status' => ElectionLifecycleStatus::Open,
        ]);
        app(ElectionEligibilitySnapshotService::class)->capture($old);

        $this->artisan('elections:process-lifecycle', ['--limit' => 100, '--fail-on-error' => true])
            ->assertExitCode(0);

        $old->refresh();
        $next = Election::query()->where('group_id', $group->id)->where('cycle_number', 2)->firstOrFail();

        $this->assertSame(ElectionLifecycleStatus::AwaitingAcceptance, $old->lifecycle_status);
        $this->assertSame(ElectionLifecycleStatus::Open, $next->lifecycle_status);
        $this->assertSame($old->id, (int) $next->previous_election_id);
        $this->assertTrue($next->ends_at->greaterThan(now()->addMonths(5)));
        $this->assertDatabaseHas('election_vote_snapshot_runs', ['election_id' => $old->id, 'snapshot_version' => 1]);
        $this->assertDatabaseHas('election_tally_results', [
            'election_id' => $old->id,
            'position' => 'manager',
            'rank' => 1,
            'within_seat_cutoff' => 1,
        ]);
        $this->assertDatabaseHas('election_responsibility_offers', [
            'election_id' => $old->id,
            'position' => 'manager',
            'ranking_position' => 1,
            'contract_version_id' => $managerContract->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('election_eligibility_snapshots', [
            'election_id' => $next->id,
            'user_id' => $voter->id,
            'voter_eligible' => 1,
        ]);

        app(ElectionBallotService::class)->submit($next, $voter->id, [$candidate->id], []);
        $this->assertDatabaseHas('votes', [
            'election_id' => $next->id,
            'voter_id' => $voter->id,
            'candidate_user_id' => $candidate->id,
            'position' => 1,
        ]);

        // This member joined only after cycle 2 had already opened. E0 says the
        // permanent election must remain available to them rather than forcing
        // them to wait until the next periodic stop.
        $lateMember = User::factory()->create(['is_system' => false]);
        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $lateMember->id,
            'role' => 1,
            'status' => 1,
        ]);
        $this->assertDatabaseMissing('election_eligibility_snapshots', [
            'election_id' => $next->id,
            'user_id' => $lateMember->id,
        ]);

        app(ElectionBallotService::class)->submit($next->refresh(), $lateMember->id, [$candidate->id], []);

        $this->assertDatabaseHas('election_eligibility_snapshots', [
            'election_id' => $next->id,
            'user_id' => $lateMember->id,
            'voter_eligible' => 1,
            'selectable_eligible' => 1,
        ]);
        $this->assertDatabaseHas('votes', [
            'election_id' => $next->id,
            'voter_id' => $lateMember->id,
            'candidate_user_id' => $candidate->id,
        ]);

        // Eligibility is symmetric while the ballot window is open: a member
        // joining after opening must also be selectable by another active voter,
        // not merely allowed to cast their own ballot.
        app(ElectionBallotService::class)->submit($next->refresh(), $voter->id, [$lateMember->id], []);
        $this->assertDatabaseHas('election_eligibility_snapshots', [
            'election_id' => $next->id,
            'user_id' => $lateMember->id,
            'voter_eligible' => 1,
            'selectable_eligible' => 1,
        ]);
        $this->assertDatabaseHas('votes', [
            'election_id' => $next->id,
            'voter_id' => $voter->id,
            'candidate_user_id' => $lateMember->id,
            'position' => 1,
        ]);
        $this->assertDatabaseHas('election_ballot_events', [
            'election_id' => $next->id,
            'voter_id' => $voter->id,
            'candidate_user_id' => $lateMember->id,
            'event_type' => 'vote_cast',
        ]);

        $this->assertSame(0, Vote::where('election_id', $old->id)->count());
        $this->assertDatabaseHas('election_ballot_events', [
            'election_id' => $next->id,
            'voter_id' => $lateMember->id,
            'candidate_user_id' => $candidate->id,
            'event_type' => 'vote_cast',
        ]);
    }
}
