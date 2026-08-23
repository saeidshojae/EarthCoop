<?php

namespace Tests\Feature\Elections;

use App\Models\Election;
use App\Models\ElectionBallotEvent;
use App\Models\ElectionPolicyVersion;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\User;
use App\Services\Elections\ElectionCandidateReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionCandidateReportPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_breakdown_is_suppressed_below_distinct_voter_threshold_and_released_at_threshold(): void
    {
        [$election, $candidate] = $this->fixture(10, 7);

        for ($i = 0; $i < 9; $i++) {
            $voter = User::factory()->create();
            ElectionBallotEvent::create([
                'election_id' => $election->id,
                'voter_id' => $voter->id,
                'event_type' => 'vote_cast',
                'candidate_user_id' => $candidate->id,
                'position' => 'manager',
                'vote_visibility' => 'confidential',
                'request_uuid' => 'report-private-'.$i,
                'occurred_at' => now()->subDays(10)->addHours($i),
            ]);
        }

        $service = app(ElectionCandidateReportService::class);
        $suppressed = $service->report($election, $candidate->id, 'manager', now()->subDays(14), now());
        $this->assertTrue($suppressed['details_suppressed']);
        $this->assertSame('distinct_voter_threshold_not_met', $suppressed['suppression_reason']);
        $this->assertNull($suppressed['distinct_voters']);
        $this->assertArrayNotHasKey('trend_buckets', $suppressed);

        $tenth = User::factory()->create();
        ElectionBallotEvent::create([
            'election_id' => $election->id,
            'voter_id' => $tenth->id,
            'event_type' => 'vote_withdrawn',
            'previous_candidate_user_id' => $candidate->id,
            'previous_position' => 'manager',
            'vote_visibility' => 'confidential',
            'request_uuid' => 'report-private-10',
            'occurred_at' => now()->subDays(8),
        ]);

        $visible = $service->report($election->fresh(), $candidate->id, 'manager', now()->subDays(14), now());
        $this->assertFalse($visible['details_suppressed']);
        $this->assertSame(10, $visible['distinct_voters']);
        $this->assertSame(9, $visible['inflow']);
        $this->assertSame(1, $visible['outflow']);
        $this->assertSame(8, $visible['net_change']);
        $this->assertTrue($visible['meaningful_trend']);
    }

    public function test_window_shorter_than_policy_bucket_is_suppressed_even_with_enough_voters(): void
    {
        [$election, $candidate] = $this->fixture(2, 7);
        foreach (range(1, 2) as $i) {
            $voter = User::factory()->create();
            ElectionBallotEvent::create([
                'election_id' => $election->id,
                'voter_id' => $voter->id,
                'event_type' => 'vote_cast',
                'candidate_user_id' => $candidate->id,
                'position' => 'manager',
                'vote_visibility' => 'confidential',
                'request_uuid' => 'short-window-'.$i,
                'occurred_at' => now()->subDay(),
            ]);
        }

        $report = app(ElectionCandidateReportService::class)
            ->report($election, $candidate->id, 'manager', now()->subDays(3), now());

        $this->assertTrue($report['details_suppressed']);
        $this->assertSame('reporting_window_too_small', $report['suppression_reason']);
    }

    private function fixture(int $minDistinct, int $bucketDays): array
    {
        $group = Group::create([
            'name' => 'E0 report group',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
        ]);
        $setting = GroupSetting::create([
            'level' => 'neighborhood',
            'manager_count' => 2,
            'inspector_count' => 1,
            'election_time' => 10,
            'max_for_election' => 20,
            'election_status' => 1,
            'second_election_time' => 3,
            'election_report_min_distinct_voters' => $minDistinct,
            'election_report_bucket_days' => $bucketDays,
            'election_meaningful_trend_min_net_change' => 3,
        ]);
        $policy = ElectionPolicyVersion::create([
            'group_setting_id' => $setting->id,
            'level_key' => 'neighborhood',
            'version' => 1,
            'election_status' => true,
            'manager_count' => 2,
            'inspector_count' => 1,
            'voting_duration_days' => 10,
            'start_threshold' => 20,
            'cycle_interval_months' => 3,
            'response_duration_days' => 7,
            'report_min_distinct_voters' => $minDistinct,
            'report_bucket_days' => $bucketDays,
            'meaningful_trend_min_net_change' => 3,
            'effective_at' => now()->subDay(),
            'change_reason' => 'report privacy test',
        ]);
        $election = Election::create([
            'group_id' => $group->id,
            'policy_version_id' => $policy->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
            'is_closed' => false,
            'lifecycle_status' => 'open',
        ]);

        return [$election, User::factory()->create()];
    }
}
