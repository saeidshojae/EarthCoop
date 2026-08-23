<?php

namespace Tests\Feature\Elections;

use App\Models\Election;
use App\Models\ElectionBallotEvent;
use App\Models\ElectionPolicyVersion;
use App\Models\ElectionVoteFeedback;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\GroupUser;
use App\Models\User;
use App\Models\Vote;
use App\Services\Elections\ElectionCandidateReportService;
use App\Services\Elections\ElectionFeedbackTopicAggregationService;
use App\Services\Elections\ElectionMeaningfulTrendNotificationService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ElectionCandidateReportAdvancedPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_retention_is_reconstructed_from_window_history_without_exposing_small_samples(): void
    {
        [$election, $candidate] = $this->fixture(2, 7, 2);
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = User::factory()->create();

        Vote::create(['election_id' => $election->id, 'voter_id' => $a->id, 'candidate_id' => $candidate->id, 'candidate_user_id' => $candidate->id, 'position' => 'manager']);
        Vote::create(['election_id' => $election->id, 'voter_id' => $c->id, 'candidate_id' => $candidate->id, 'candidate_user_id' => $candidate->id, 'position' => 'manager']);

        ElectionBallotEvent::create([
            'election_id' => $election->id, 'voter_id' => $b->id, 'event_type' => 'vote_withdrawn',
            'previous_candidate_user_id' => $candidate->id, 'previous_position' => 'manager',
            'vote_visibility' => 'confidential', 'request_uuid' => 'retention-out', 'occurred_at' => now()->subDays(5),
        ]);
        ElectionBallotEvent::create([
            'election_id' => $election->id, 'voter_id' => $c->id, 'event_type' => 'vote_cast',
            'candidate_user_id' => $candidate->id, 'position' => 'manager',
            'vote_visibility' => 'confidential', 'request_uuid' => 'retention-in', 'occurred_at' => now()->subDays(3),
        ]);

        $report = app(ElectionCandidateReportService::class)
            ->report($election, $candidate->id, 'manager', now()->subDays(7), now());

        $this->assertFalse($report['details_suppressed']);
        $this->assertFalse($report['retention_suppressed']);
        $this->assertSame(0.5, $report['retention_rate']);
    }

    public function test_topic_aggregation_respects_visibility_and_distinct_author_threshold(): void
    {
        [$election, $candidate] = $this->fixture(2, 7, 2);
        GroupUser::create(['group_id' => $election->group_id, 'user_id' => $candidate->id, 'role' => 1, 'status' => 1]);

        $authors = [User::factory()->create(), User::factory()->create()];
        foreach ($authors as $index => $author) {
            GroupUser::create(['group_id' => $election->group_id, 'user_id' => $author->id, 'role' => 1, 'status' => 1]);
            $event = ElectionBallotEvent::create([
                'election_id' => $election->id, 'voter_id' => $author->id, 'event_type' => 'vote_cast',
                'candidate_user_id' => $candidate->id, 'position' => 'manager', 'vote_visibility' => 'confidential',
                'comment' => $index === 0 ? 'شفافیت گزارش‌ها بهتر شود' : 'پاسخگویی و پیگیری بهتر شود',
                'comment_visibility' => 'all_members', 'comment_anonymous' => true,
                'request_uuid' => 'topic-'.$index, 'occurred_at' => now()->subDays(4 - $index),
            ]);

            $feedback = ElectionVoteFeedback::query()->where('ballot_event_id', $event->id)->firstOrFail();
            $feedback->forceFill([
                'election_id' => $election->id,
                'author_user_id' => $author->id,
                'subject_user_id' => $candidate->id,
                'event_type' => 'vote_cast',
                'visibility' => 'all_members',
                'anonymous' => true,
                'body' => $event->comment,
                'moderation_status' => 'approved',
                'moderation_source' => 'test',
                'moderated_at' => now()->subDays(2),
                'published_at' => now()->subDays(2),
                'public_bucket_start' => now()->subDays(7)->startOfDay(),
            ])->save();
        }

        $report = app(ElectionFeedbackTopicAggregationService::class)
            ->aggregate($election, $candidate->id, $candidate, now()->subDays(7), now());

        $this->assertFalse($report['topics_suppressed']);
        $topics = collect($report['topics'])->pluck('topic')->all();
        $this->assertContains('transparency', $topics);
        $this->assertContains('responsiveness', $topics);
        $this->assertArrayNotHasKey('author_user_id', $report);
    }

    public function test_meaningful_trend_alert_is_deduplicated_per_privacy_safe_bucket(): void
    {
        [$election, $candidate] = $this->fixture(2, 7, 2);
        foreach (range(1, 2) as $i) {
            $voter = User::factory()->create();
            Vote::create(['election_id' => $election->id, 'voter_id' => $voter->id, 'candidate_id' => $candidate->id, 'candidate_user_id' => $candidate->id, 'position' => 'manager']);
            ElectionBallotEvent::create([
                'election_id' => $election->id, 'voter_id' => $voter->id, 'event_type' => 'vote_cast',
                'candidate_user_id' => $candidate->id, 'position' => 'manager', 'vote_visibility' => 'confidential',
                'request_uuid' => 'trend-'.$i, 'occurred_at' => now()->subDays($i),
            ]);
        }

        $notifications = Mockery::mock(NotificationService::class);
        $notifications->shouldReceive('notifyUser')->once();
        $service = new ElectionMeaningfulTrendNotificationService(app(ElectionCandidateReportService::class), $notifications);

        $first = $service->evaluateAndNotify($election, $candidate->id, 'manager');
        $second = $service->evaluateAndNotify($election, $candidate->id, 'manager');

        $this->assertNotNull($first);
        $this->assertNotNull($first->notified_at);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('election_trend_alerts', 1);
    }

    private function fixture(int $minDistinct, int $bucketDays, int $meaningfulNet): array
    {
        $group = Group::create(['name' => 'E0 advanced report group', 'group_type' => 'public', 'location_level' => 'neighborhood']);
        $setting = GroupSetting::create([
            'level' => 'neighborhood', 'manager_count' => 2, 'inspector_count' => 1,
            'election_time' => 10, 'max_for_election' => 20, 'election_status' => 1, 'second_election_time' => 3,
            'election_report_min_distinct_voters' => $minDistinct,
            'election_report_bucket_days' => $bucketDays,
            'election_meaningful_trend_min_net_change' => $meaningfulNet,
        ]);
        $policy = ElectionPolicyVersion::create([
            'group_setting_id' => $setting->id, 'level_key' => 'neighborhood', 'version' => 1,
            'election_status' => true, 'manager_count' => 2, 'inspector_count' => 1,
            'voting_duration_days' => 10, 'start_threshold' => 20, 'cycle_interval_months' => 3,
            'response_duration_days' => 7, 'report_min_distinct_voters' => $minDistinct,
            'report_bucket_days' => $bucketDays, 'meaningful_trend_min_net_change' => $meaningfulNet,
            'effective_at' => now()->subDay(), 'change_reason' => 'advanced report test',
        ]);
        $election = Election::create([
            'group_id' => $group->id, 'policy_version_id' => $policy->id,
            'starts_at' => now()->subMonth(), 'ends_at' => now()->addMonth(),
            'is_closed' => false, 'lifecycle_status' => 'open',
        ]);

        return [$election, User::factory()->create()];
    }
}
