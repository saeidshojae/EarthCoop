<?php

namespace Tests\Feature\Elections;

use App\Models\Election;
use App\Models\ElectionBallotEvent;
use App\Models\ElectionVoteFeedback;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\Elections\ElectionVoteFeedbackReadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionVoteFeedbackModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_anonymous_subject_feedback_is_independent_approved_and_hides_author_and_raw_time(): void
    {
        [$election, $author, $subject, $member] = $this->fixture();

        $event = ElectionBallotEvent::create([
            'election_id' => $election->id,
            'voter_id' => $author->id,
            'event_type' => 'vote_withdrawn',
            'previous_candidate_user_id' => $subject->id,
            'previous_position' => 'manager',
            'vote_visibility' => 'confidential',
            'comment' => 'در هفته‌های اخیر پاسخ‌گویی و گزارش‌دهی منظم نبود.',
            'comment_visibility' => 'subject_only',
            'comment_anonymous' => true,
            'request_uuid' => 'feedback-clean-1',
            'occurred_at' => now(),
        ]);

        $feedback = ElectionVoteFeedback::where('ballot_event_id', $event->id)->firstOrFail();
        $this->assertSame('approved', $feedback->moderation_status);
        $this->assertNotNull($feedback->published_at);
        $this->assertNotNull($feedback->public_bucket_start);

        $reader = app(ElectionVoteFeedbackReadService::class);
        $subjectView = $reader->present($feedback, $subject);
        $this->assertNotNull($subjectView);
        $this->assertNull($subjectView['author_user_id']);
        $this->assertArrayNotHasKey('ballot_event_id', $subjectView);
        $this->assertArrayNotHasKey('occurred_at', $subjectView);
        $this->assertNull($reader->present($feedback, $member));

        $authorView = $reader->present($feedback, $author);
        $this->assertSame($author->id, $authorView['author_user_id']);
    }

    public function test_suspicious_feedback_is_held_for_human_review_and_not_exposed_to_subject(): void
    {
        [$election, $author, $subject] = $this->fixture();

        $event = ElectionBallotEvent::create([
            'election_id' => $election->id,
            'voter_id' => $author->id,
            'event_type' => 'vote_changed',
            'candidate_user_id' => $subject->id,
            'position' => 'manager',
            'vote_visibility' => 'confidential',
            'comment' => 'برای تماس مستقیم با من test@example.com را استفاده کن.',
            'comment_visibility' => 'subject_only',
            'comment_anonymous' => true,
            'request_uuid' => 'feedback-review-1',
            'occurred_at' => now(),
        ]);

        $feedback = ElectionVoteFeedback::where('ballot_event_id', $event->id)->firstOrFail();
        $this->assertSame('pending_review', $feedback->moderation_status);
        $this->assertContains('possible_personal_information', $feedback->moderation_reasons);
        $this->assertNull($feedback->published_at);

        $reader = app(ElectionVoteFeedbackReadService::class);
        $this->assertNull($reader->present($feedback, $subject));
        $this->assertSame('pending_review', $reader->present($feedback, $author)['moderation_status']);
    }

    private function fixture(): array
    {
        $group = Group::create([
            'name' => 'E0 feedback group',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
        ]);
        $election = Election::create([
            'group_id' => $group->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_closed' => false,
            'lifecycle_status' => 'open',
        ]);

        $author = User::factory()->create();
        $subject = User::factory()->create();
        $member = User::factory()->create();
        foreach ([$author, $subject, $member] as $user) {
            GroupUser::create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role' => 1,
                'status' => 1,
            ]);
        }

        return [$election, $author, $subject, $member];
    }
}
