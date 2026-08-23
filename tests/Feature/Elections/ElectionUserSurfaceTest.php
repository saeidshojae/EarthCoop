<?php

namespace Tests\Feature\Elections;

use App\Http\Controllers\Group\SystemicElectionChatController;
use App\Models\Election;
use App\Models\ElectionBallotEvent;
use App\Models\ElectionLifecycleTransition;
use App\Models\ElectionProcessReview;
use App\Models\ElectionVoteFeedback;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ElectionUserSurfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_group_chat_route_is_shadowed_by_read_only_systemic_election_presenter(): void
    {
        $route = Route::getRoutes()->getByName('groups.chat');

        $this->assertNotNull($route);
        $this->assertSame(SystemicElectionChatController::class.'@chat', $route->getActionName());

        $source = file_get_contents(app_path('Http/Controllers/Group/SystemicElectionChatController.php'));
        $this->assertStringNotContainsString('Election::firstOrCreate', $source);
        $this->assertStringNotContainsString('second_finish_time', $source);
        $this->assertStringNotContainsString('finishElection', $source);
    }

    public function test_systemic_ballot_view_has_no_client_side_lifecycle_mutation_and_exposes_privacy_controls(): void
    {
        $source = file_get_contents(resource_path('views/groups/modals/election_modal.blade.php'));

        $this->assertStringNotContainsString('/finish-election/', $source);
        $this->assertStringNotContainsString('finishElectionAjax', $source);
        $this->assertStringNotContainsString('second_finish_time', $source);
        $this->assertStringNotContainsString('GroupSetting::', $source);
        $this->assertStringNotContainsString('.addEventListener(', $source);
        $this->assertStringNotContainsString('setTimeout(', $source);
        $this->assertStringContainsString('window.GroupElectionModal = {', $source);
        $this->assertStringContainsString('vote_visibility[', $source);
        $this->assertStringContainsString('data-election-role="manager"', $source);
        $this->assertStringContainsString('data-election-role="inspector"', $source);
        $this->assertStringContainsString('comment_visibility', $source);
        $this->assertStringContainsString('comment_anonymous', $source);
        $this->assertStringContainsString('subject_only', $source);
        $this->assertStringContainsString('data-systemic-election-ballot', $source);
    }

    public function test_user_portal_renders_for_active_group_member(): void
    {
        [$group, $member, $election] = $this->fixture();

        $this->actingAs($member)
            ->get(route('elections.portal', $group))
            ->assertOk()
            ->assertSee('انتخابات سیستمی', false)
            ->assertSee((string) $election->id, false)
            ->assertSee('بازبینی رویه‌ای', false)
            ->assertSee('نظرها و دلایل رأی', false);
    }

    public function test_portal_feedback_surface_obeys_visibility_policy_and_anonymity(): void
    {
        [$group, $viewer, $election] = $this->fixture();
        $author = User::factory()->create(['is_system' => false]);
        $otherSubject = User::factory()->create(['is_system' => false]);
        foreach ([$author, $otherSubject] as $user) {
            GroupUser::create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role' => 1,
                'status' => 1,
            ]);
        }

        $publicEvent = ElectionBallotEvent::create([
            'election_id' => $election->id,
            'voter_id' => $author->id,
            'event_type' => 'vote_cast',
            'candidate_user_id' => $otherSubject->id,
            'position' => 'manager',
            'vote_visibility' => 'confidential',
            'comment' => 'نظر عمومی قابل مشاهده برای اعضا',
            'comment_visibility' => 'all_members',
            'comment_anonymous' => true,
            'request_uuid' => 'portal-public-feedback',
            'occurred_at' => now()->subDays(2),
        ]);
        $publicFeedback = ElectionVoteFeedback::query()
            ->where('ballot_event_id', $publicEvent->id)
            ->firstOrFail();
        $publicFeedback->forceFill([
            'moderation_status' => 'approved',
            'moderation_source' => 'test',
            'moderated_at' => now()->subDay(),
            'published_at' => now()->subDay(),
            'public_bucket_start' => now()->startOfWeek(),
        ])->save();

        $privateEvent = ElectionBallotEvent::create([
            'election_id' => $election->id,
            'voter_id' => $author->id,
            'event_type' => 'vote_cast',
            'candidate_user_id' => $otherSubject->id,
            'position' => 'manager',
            'vote_visibility' => 'confidential',
            'comment' => 'این متن فقط برای موضوع رأی است',
            'comment_visibility' => 'subject_only',
            'comment_anonymous' => false,
            'request_uuid' => 'portal-private-feedback',
            'occurred_at' => now()->subDay(),
        ]);
        $privateFeedback = ElectionVoteFeedback::query()
            ->where('ballot_event_id', $privateEvent->id)
            ->firstOrFail();
        $privateFeedback->forceFill([
            'moderation_status' => 'approved',
            'moderation_source' => 'test',
            'moderated_at' => now()->subDay(),
            'published_at' => now()->subDay(),
            'public_bucket_start' => now()->startOfWeek(),
        ])->save();

        $this->actingAs($viewer)
            ->get(route('elections.portal', $group))
            ->assertOk()
            ->assertSee('نظر عمومی قابل مشاهده برای اعضا', false)
            ->assertSee('نویسنده: ناشناس', false)
            ->assertDontSee('این متن فقط برای موضوع رأی است', false);

        $this->actingAs($otherSubject)
            ->get(route('elections.portal', $group))
            ->assertOk()
            ->assertSee('این متن فقط برای موضوع رأی است', false);
    }

    public function test_review_deadline_is_anchored_to_immutable_event_not_spoofed_timestamp(): void
    {
        [$group, $member, $election] = $this->fixture();
        $occurredAt = now()->subDays(5)->startOfSecond();
        $transition = ElectionLifecycleTransition::create([
            'election_id' => $election->id,
            'from_status' => 'scheduled',
            'to_status' => 'open',
            'reason' => 'test evidence',
            'source' => 'system',
            'transitioned_at' => $occurredAt,
        ]);

        $this->actingAs($member)
            ->postJson(route('elections.process-reviews.store', $election), [
                'ground' => 'technical_error',
                'challenged_event' => 'lifecycle_transition',
                'challenged_event_id' => $transition->id,
                'event_occurred_at' => now()->toISOString(),
                'statement' => 'زمان مهلت باید از audit واقعی گرفته شود.',
            ])
            ->assertCreated()
            ->assertJsonPath('challenged_event_id', $transition->id);

        $review = ElectionProcessReview::query()->latest('id')->firstOrFail();
        $this->assertTrue($review->event_occurred_at->equalTo($occurredAt));
        $this->assertTrue($review->human_deadline_at->equalTo($occurredAt->copy()->addDays(7)));
    }

    public function test_review_event_from_another_election_is_rejected(): void
    {
        [$group, $member, $election] = $this->fixture();
        $other = Election::create([
            'group_id' => $group->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_closed' => false,
            'lifecycle_status' => 'open',
        ]);
        $transition = ElectionLifecycleTransition::create([
            'election_id' => $other->id,
            'from_status' => 'scheduled',
            'to_status' => 'open',
            'reason' => 'other election',
            'source' => 'system',
            'transitioned_at' => now()->subHour(),
        ]);

        $this->actingAs($member)
            ->postJson(route('elections.process-reviews.store', $election), [
                'ground' => 'technical_error',
                'challenged_event' => 'lifecycle_transition',
                'challenged_event_id' => $transition->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('challenged_event_id');

        $this->assertDatabaseCount('election_process_reviews', 0);
    }

    private function fixture(): array
    {
        $group = Group::create([
            'name' => 'Election user surface group',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
        ]);
        $member = User::factory()->create(['is_system' => false]);
        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $member->id,
            'role' => 1,
            'status' => 1,
        ]);
        $election = Election::create([
            'group_id' => $group->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(5),
            'is_closed' => false,
            'lifecycle_status' => 'open',
            'cycle_number' => 1,
        ]);

        return [$group, $member, $election];
    }
}
