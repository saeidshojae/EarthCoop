<?php

namespace Tests\Feature\Elections;

use App\Enums\Elections\ElectionBallotCommentVisibility;
use App\Enums\Elections\ElectionLifecycleStatus;
use App\Enums\Elections\ElectionVoteVisibility;
use App\Models\Election;
use App\Models\ElectionBallotEvent;
use App\Models\ElectionEligibilitySnapshot;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\User;
use App\Models\Vote;
use App\Services\Elections\ElectionBallotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionBallotPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_vote_disclosure_is_per_selection_and_rationale_anonymity_is_independent(): void
    {
        $group = Group::create(['name' => 'Privacy group', 'group_type' => 'public', 'location_level' => 'neighborhood']);
        GroupSetting::create([
            'level' => 'neighborhood', 'manager_count' => 2, 'inspector_count' => 1,
            'election_time' => 10, 'max_for_election' => 1, 'election_status' => 1,
        ]);
        $election = Election::create([
            'group_id' => $group->id,
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addDay(),
            'is_closed' => false,
            'lifecycle_status' => ElectionLifecycleStatus::Open,
            'eligibility_snapshot_captured_at' => now(),
            'eligibility_snapshot_version' => 1,
        ]);

        $voter = User::factory()->create();
        $candidateA = User::factory()->create();
        $candidateB = User::factory()->create();
        foreach ([$voter, $candidateA, $candidateB] as $user) {
            ElectionEligibilitySnapshot::create([
                'election_id' => $election->id,
                'user_id' => $user->id,
                'voter_eligible' => true,
                'selectable_eligible' => true,
                'membership_role' => 1,
                'membership_status' => 1,
                'snapshot_version' => 1,
                'captured_at' => now(),
            ]);
        }

        app(ElectionBallotService::class)->submit(
            $election,
            $voter->id,
            [$candidateA->id, $candidateB->id],
            [],
            'privacy-1',
            'دلیل اختیاری',
            ElectionBallotCommentVisibility::SubjectOnly,
            [
                $candidateA->id => ElectionVoteVisibility::Confidential->value,
                $candidateB->id => ElectionVoteVisibility::AllMembers->value,
            ],
            true,
        );

        $this->assertDatabaseHas('votes', [
            'election_id' => $election->id,
            'candidate_user_id' => $candidateA->id,
            'vote_visibility' => ElectionVoteVisibility::Confidential->value,
        ]);
        $this->assertDatabaseHas('votes', [
            'election_id' => $election->id,
            'candidate_user_id' => $candidateB->id,
            'vote_visibility' => ElectionVoteVisibility::AllMembers->value,
        ]);

        $events = ElectionBallotEvent::where('request_uuid', 'privacy-1')->get();
        $this->assertCount(2, $events);
        $this->assertTrue($events->every(fn ($event) => $event->comment_anonymous === true));
        $this->assertTrue($events->every(fn ($event) => $event->comment_visibility === ElectionBallotCommentVisibility::SubjectOnly));
    }

    public function test_omitted_disclosure_defaults_to_confidential_for_legacy_compatibility(): void
    {
        $group = Group::create(['name' => 'Privacy fallback group', 'group_type' => 'public', 'location_level' => 'neighborhood']);
        GroupSetting::create([
            'level' => 'neighborhood', 'manager_count' => 1, 'inspector_count' => 1,
            'election_time' => 10, 'max_for_election' => 1, 'election_status' => 1,
        ]);
        $election = Election::create([
            'group_id' => $group->id,
            'starts_at' => now()->subMinute(), 'ends_at' => now()->addDay(),
            'is_closed' => false, 'lifecycle_status' => ElectionLifecycleStatus::Open,
            'eligibility_snapshot_captured_at' => now(), 'eligibility_snapshot_version' => 1,
        ]);
        $voter = User::factory()->create();
        $candidate = User::factory()->create();
        foreach ([$voter, $candidate] as $user) {
            ElectionEligibilitySnapshot::create([
                'election_id' => $election->id, 'user_id' => $user->id,
                'voter_eligible' => true, 'selectable_eligible' => true,
                'membership_role' => 1, 'membership_status' => 1,
                'snapshot_version' => 1, 'captured_at' => now(),
            ]);
        }

        app(ElectionBallotService::class)->submit($election, $voter->id, [$candidate->id], [], 'privacy-default');

        $this->assertSame(
            ElectionVoteVisibility::Confidential,
            Vote::where('election_id', $election->id)->firstOrFail()->vote_visibility,
        );
    }
}
