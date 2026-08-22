<?php

namespace Tests\Feature\Elections;

use App\Models\Election;
use App\Models\ElectionBallotEvent;
use App\Models\ElectionEligibilitySnapshot;
use App\Models\ElectionPolicyVersion;
use App\Models\ElectionTallyResult;
use App\Models\ElectionVoteFeedback;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\Elections\ElectionFeedbackTopicResponseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ElectionFeedbackTopicResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_can_reply_only_to_privacy_safe_public_aggregate_topic_without_feedback_linkage(): void
    {
        [$election, $candidate] = $this->fixture(2);
        foreach (range(1, 2) as $i) {
            $author = User::factory()->create();
            GroupUser::create(['group_id' => $election->group_id, 'user_id' => $author->id, 'role' => 1, 'status' => 1]);
            $event = $this->ballotEvent($election, $author, $candidate, 'vote_cast');
            ElectionVoteFeedback::create([
                'election_id' => $election->id,
                'ballot_event_id' => $event->id,
                'author_user_id' => $author->id,
                'subject_user_id' => $candidate->id,
                'event_type' => 'vote_cast',
                'visibility' => 'all_members',
                'anonymous' => true,
                'body' => 'شفافیت گزارش‌ها باید بهتر شود',
                'moderation_status' => 'approved',
                'moderation_source' => 'test',
                'moderated_at' => now()->subDays(2),
                'published_at' => now()->subDays(2),
                'public_bucket_start' => now()->subDays(7)->startOfDay(),
            ]);
        }

        $response = app(ElectionFeedbackTopicResponseService::class)
            ->publish($election, $candidate, 'transparency', 'گزارش دوره‌ای شفاف‌تری منتشر خواهم کرد.');

        $this->assertSame('transparency', $response->topic_key);
        $this->assertSame(2, (int) $response->aggregate_count);
        $this->assertDatabaseHas('election_feedback_topic_responses', ['id' => $response->id, 'subject_user_id' => $candidate->id]);
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('election_feedback_topic_responses');
        $this->assertNotContains('feedback_id', $columns);
        $this->assertNotContains('ballot_event_id', $columns);
        $this->assertNotContains('author_user_id', $columns);
    }

    public function test_private_feedback_cannot_become_a_public_topic_response(): void
    {
        [$election, $candidate] = $this->fixture(2);
        foreach (range(1, 2) as $i) {
            $author = User::factory()->create();
            GroupUser::create(['group_id' => $election->group_id, 'user_id' => $author->id, 'role' => 1, 'status' => 1]);
            $event = $this->ballotEvent($election, $author, $candidate, 'vote_withdrawn');
            ElectionVoteFeedback::create([
                'election_id' => $election->id,
                'ballot_event_id' => $event->id,
                'author_user_id' => $author->id,
                'subject_user_id' => $candidate->id,
                'event_type' => 'vote_withdrawn',
                'visibility' => 'subject_only',
                'anonymous' => true,
                'body' => 'شفافیت گزارش‌ها باید بهتر شود',
                'moderation_status' => 'approved',
                'moderation_source' => 'test',
                'moderated_at' => now()->subDays(2),
                'published_at' => now()->subDays(2),
                'public_bucket_start' => now()->subDays(7)->startOfDay(),
            ]);
        }

        $this->expectException(RuntimeException::class);
        app(ElectionFeedbackTopicResponseService::class)
            ->publish($election, $candidate, 'transparency', 'پاسخ عمومی');
    }

    private function ballotEvent(Election $election, User $author, User $candidate, string $eventType): ElectionBallotEvent
    {
        return ElectionBallotEvent::create([
            'election_id' => $election->id,
            'voter_id' => $author->id,
            'event_type' => $eventType,
            'candidate_user_id' => $eventType === 'vote_withdrawn' ? null : $candidate->id,
            'previous_candidate_user_id' => $eventType === 'vote_withdrawn' ? $candidate->id : null,
            'position' => $eventType === 'vote_withdrawn' ? null : 'manager',
            'previous_position' => $eventType === 'vote_withdrawn' ? 'manager' : null,
            'vote_visibility' => 'confidential',
            'request_uuid' => (string) Str::uuid(),
            'metadata' => ['source' => 'topic_response_test'],
            'occurred_at' => now()->subDays(2),
        ]);
    }

    private function fixture(int $minDistinct): array
    {
        $group = Group::create(['name' => 'topic response group', 'group_type' => 'public', 'location_level' => 'neighborhood']);
        $setting = GroupSetting::create([
            'level' => 'neighborhood', 'manager_count' => 2, 'inspector_count' => 1,
            'election_time' => 10, 'max_for_election' => 20, 'election_status' => 1, 'second_election_time' => 3,
            'election_report_min_distinct_voters' => $minDistinct,
            'election_report_bucket_days' => 7,
            'election_meaningful_trend_min_net_change' => 2,
        ]);
        $policy = ElectionPolicyVersion::create([
            'group_setting_id' => $setting->id, 'level_key' => 'neighborhood', 'version' => 1,
            'election_status' => true, 'manager_count' => 2, 'inspector_count' => 1,
            'voting_duration_days' => 10, 'start_threshold' => 20, 'cycle_interval_months' => 3,
            'response_duration_days' => 7, 'report_min_distinct_voters' => $minDistinct,
            'report_bucket_days' => 7, 'meaningful_trend_min_net_change' => 2,
            'effective_at' => now()->subDay(), 'change_reason' => 'topic response test',
        ]);
        $election = Election::create([
            'group_id' => $group->id, 'policy_version_id' => $policy->id,
            'starts_at' => now()->subMonth(), 'ends_at' => now()->addMonth(),
            'is_closed' => false, 'lifecycle_status' => 'open',
        ]);
        $candidate = User::factory()->create();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $candidate->id, 'role' => 1, 'status' => 1]);
        ElectionEligibilitySnapshot::create([
            'election_id' => $election->id, 'user_id' => $candidate->id,
            'active_member' => true, 'voter_eligible' => true, 'selectable_eligible' => true,
            'captured_at' => now()->subDays(8), 'snapshot_version' => 1,
        ]);
        ElectionTallyResult::create([
            'election_id' => $election->id, 'candidate_user_id' => $candidate->id,
            'position' => 'manager', 'vote_count' => 2, 'rank' => 1, 'within_seat_cutoff' => true,
            'cycle_identifier' => 'topic-response-test', 'stopped_at' => now()->subDays(7),
            'vote_snapshot_hash' => str_repeat('a', 64), 'draw_seed_version' => 'test',
            'draw_seed' => str_repeat('b', 64), 'tie_break_version' => 'test',
            'tie_break_key' => str_repeat('c', 64), 'tallied_at' => now()->subDays(7),
        ]);

        return [$election, $candidate];
    }
}
