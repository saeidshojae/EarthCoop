<?php

namespace Tests\Feature\Elections;

use App\Models\Election;
use App\Models\ElectionPolicyVersion;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\User;
use App\Models\Vote;
use App\Services\Elections\ElectionCandidateReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionCandidateReportPositionCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_counts_real_legacy_position_rows_written_by_canonical_ballot_flow(): void
    {
        $group = Group::create([
            'name' => 'Position compatibility group',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
        ]);
        $setting = GroupSetting::create([
            'level' => 'neighborhood',
            'manager_count' => 1,
            'inspector_count' => 1,
            'election_time' => 30,
            'max_for_election' => 1,
            'election_status' => 1,
            'second_election_time' => 6,
        ]);
        $policy = ElectionPolicyVersion::create([
            'group_setting_id' => $setting->id,
            'level_key' => 'neighborhood',
            'version' => 1,
            'election_status' => true,
            'manager_count' => 1,
            'inspector_count' => 1,
            'voting_duration_days' => 30,
            'start_threshold' => 1,
            'cycle_interval_months' => 6,
            'response_duration_days' => 7,
            'report_min_distinct_voters' => 10,
            'report_bucket_days' => 7,
            'meaningful_trend_min_net_change' => 3,
            'effective_at' => now()->subDay(),
            'change_reason' => 'position compatibility test',
        ]);
        $election = Election::create([
            'group_id' => $group->id,
            'policy_version_id' => $policy->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(29),
            'is_closed' => false,
            'lifecycle_status' => 'open',
        ]);
        $candidate = User::factory()->create(['is_system' => false]);
        $voters = User::factory()->count(2)->create(['is_system' => false]);

        foreach ($voters as $voter) {
            Vote::create([
                'election_id' => $election->id,
                'voter_id' => $voter->id,
                'candidate_id' => $candidate->id,
                'candidate_user_id' => $candidate->id,
                // ElectionBallotService persists manager through legacyVotePosition() = 1.
                'position' => 1,
                'vote_visibility' => 'confidential',
            ]);
        }

        $report = app(ElectionCandidateReportService::class)->report($election, $candidate->id, 'manager');

        $this->assertSame(2, $report['current_votes']);
        $this->assertSame(2, $report['selection_cutoff_votes']);
        $this->assertSame(0, $report['margin_to_selection_cutoff']);
        $this->assertSame('manager', $report['position']);
    }
}
