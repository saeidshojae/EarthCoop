<?php

namespace Tests\Feature\Elections;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Models\Election;
use App\Models\ElectionEligibilitySnapshot;
use App\Models\ElectionPolicyVersion;
use App\Models\ElectionTallyResult;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\GroupUser;
use App\Models\User;
use App\Models\Vote;
use App\Services\Elections\ElectionLifecycleService;
use App\Services\Elections\ElectionProcessReviewService;
use App\Services\Elections\ElectionTallyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionFrozenPolicyRecountTest extends TestCase
{
    use RefreshDatabase;

    public function test_tally_and_later_recount_keep_original_cycle_seat_policy_when_a_new_cycle_uses_different_policy(): void
    {
        $group = Group::create([
            'name' => 'Frozen policy recount group',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
        ]);
        $setting = GroupSetting::create([
            'level' => 'neighborhood',
            'manager_count' => 1,
            'inspector_count' => 0,
            'election_time' => 30,
            'max_for_election' => 1,
            'election_status' => 1,
            'second_election_time' => 6,
        ]);
        $policyOne = $this->policy($setting, 1, 1);

        $voter = User::factory()->create(['is_system' => false]);
        $candidateA = User::factory()->create(['is_system' => false]);
        $candidateB = User::factory()->create(['is_system' => false]);
        foreach ([$voter, $candidateA, $candidateB] as $user) {
            GroupUser::create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role' => 1,
                'status' => 1,
            ]);
        }

        $oldElection = Election::create([
            'group_id' => $group->id,
            'cycle_number' => 1,
            'policy_version_id' => $policyOne->id,
            'starts_at' => now()->subDays(30),
            'ends_at' => now(),
            'is_closed' => false,
            'lifecycle_status' => ElectionLifecycleStatus::Open,
            'eligibility_snapshot_captured_at' => now()->subDays(30),
            'eligibility_snapshot_version' => 1,
        ]);
        foreach ([$voter, $candidateA, $candidateB] as $user) {
            ElectionEligibilitySnapshot::create([
                'election_id' => $oldElection->id,
                'user_id' => $user->id,
                'active_member' => true,
                'voter_eligible' => true,
                'selectable_eligible' => in_array($user->id, [$candidateA->id, $candidateB->id], true),
                'membership_role' => 1,
                'membership_status' => 1,
                'snapshot_version' => 1,
                'captured_at' => now()->subDays(30),
            ]);
        }
        Vote::create([
            'election_id' => $oldElection->id,
            'voter_id' => $voter->id,
            'candidate_id' => $candidateA->id,
            'candidate_user_id' => $candidateA->id,
            'position' => 1,
            'vote_visibility' => 'confidential',
        ]);

        $oldElection = app(ElectionLifecycleService::class)->transition(
            $oldElection,
            ElectionLifecycleStatus::Closed,
            'frozen_policy_test_stop',
            'test',
        );
        app(ElectionTallyService::class)->tally($oldElection);

        $this->assertDatabaseHas('election_tally_results', [
            'election_id' => $oldElection->id,
            'candidate_user_id' => $candidateA->id,
            'position' => 'manager',
            'rank' => 1,
            'within_seat_cutoff' => 1,
        ]);
        $this->assertDatabaseHas('election_tally_results', [
            'election_id' => $oldElection->id,
            'candidate_user_id' => $candidateB->id,
            'position' => 'manager',
            'rank' => 2,
            'within_seat_cutoff' => 0,
        ]);

        // A later policy and simultaneously collecting successor must not alter
        // the old cycle's recount cut-off.
        $setting->update(['manager_count' => 2]);
        $policyTwo = $this->policy($setting, 2, 2);
        Election::create([
            'group_id' => $group->id,
            'cycle_number' => 2,
            'previous_election_id' => $oldElection->id,
            'policy_version_id' => $policyTwo->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonths(6),
            'is_closed' => false,
            'lifecycle_status' => ElectionLifecycleStatus::Open,
        ]);

        $review = app(ElectionProcessReviewService::class)->openAutomaticReview(
            $oldElection->refresh(),
            $voter,
            'ranking',
            'stop_tally',
            now()->subMinute(),
            null,
            $candidateB->id,
        );

        $this->assertSame('verified', $review->automatic_status);
        $this->assertTrue((bool) ($review->automatic_result['tally_integrity'] ?? false));
        $this->assertSame(2, ElectionTallyResult::where('election_id', $oldElection->id)->where('position', 'manager')->count());
    }

    private function policy(GroupSetting $setting, int $version, int $managerCount): ElectionPolicyVersion
    {
        return ElectionPolicyVersion::create([
            'group_setting_id' => $setting->id,
            'level_key' => 'neighborhood',
            'version' => $version,
            'election_status' => true,
            'manager_count' => $managerCount,
            'inspector_count' => 0,
            'voting_duration_days' => 30,
            'start_threshold' => 1,
            'cycle_interval_months' => 6,
            'response_duration_days' => 7,
            'report_min_distinct_voters' => 10,
            'report_bucket_days' => 7,
            'meaningful_trend_min_net_change' => 3,
            'effective_at' => now()->subMinute(),
            'change_reason' => 'frozen policy recount test',
        ]);
    }
}
