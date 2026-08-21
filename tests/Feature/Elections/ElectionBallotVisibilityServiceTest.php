<?php

namespace Tests\Feature\Elections;

use App\Enums\Elections\ElectionBallotCommentVisibility;
use App\Enums\Elections\ElectionVoteVisibility;
use App\Models\Election;
use App\Models\ElectionAppointment;
use App\Models\ElectionBallotEvent;
use App\Models\ElectionResponsibilityContractVersion;
use App\Models\ElectionResponsibilityOffer;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\Elections\ElectionBallotVisibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionBallotVisibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_confidential_vote_identity_is_hidden_from_members_candidates_and_officials(): void
    {
        [$event, $voter, $candidate, $member, $official, $outsider] = $this->fixture(
            ElectionVoteVisibility::Confidential,
            ElectionBallotCommentVisibility::AllMembers,
            false,
        );

        $privacy = app(ElectionBallotVisibilityService::class);

        $this->assertSame($voter->id, $privacy->visibleVoterId($event, $voter));
        $this->assertNull($privacy->visibleVoterId($event, $candidate));
        $this->assertNull($privacy->visibleVoterId($event, $member));
        $this->assertNull($privacy->visibleVoterId($event, $official));
        $this->assertNull($privacy->visibleVoterId($event, $outsider));
    }

    public function test_all_members_vote_identity_is_visible_only_to_active_group_members(): void
    {
        [$event, $voter, $candidate, $member, $official, $outsider] = $this->fixture(
            ElectionVoteVisibility::AllMembers,
            ElectionBallotCommentVisibility::AllMembers,
            false,
        );

        $privacy = app(ElectionBallotVisibilityService::class);

        $this->assertSame($voter->id, $privacy->visibleVoterId($event, $member));
        $this->assertSame($voter->id, $privacy->visibleVoterId($event, $official));
        $this->assertSame($voter->id, $privacy->visibleVoterId($event, $candidate));
        $this->assertNull($privacy->visibleVoterId($event, $outsider));
    }

    public function test_elected_officials_vote_identity_is_not_visible_to_ordinary_members_or_candidate_without_office(): void
    {
        [$event, $voter, $candidate, $member, $official, $outsider] = $this->fixture(
            ElectionVoteVisibility::ElectedOfficials,
            ElectionBallotCommentVisibility::ElectedOfficials,
            false,
        );

        $privacy = app(ElectionBallotVisibilityService::class);

        $this->assertSame($voter->id, $privacy->visibleVoterId($event, $official));
        $this->assertNull($privacy->visibleVoterId($event, $member));
        $this->assertNull($privacy->visibleVoterId($event, $candidate));
        $this->assertNull($privacy->visibleVoterId($event, $outsider));
    }

    public function test_subject_only_anonymous_comment_is_visible_to_target_without_author_identity(): void
    {
        [$event, $voter, $candidate, $member, $official, $outsider] = $this->fixture(
            ElectionVoteVisibility::Confidential,
            ElectionBallotCommentVisibility::SubjectOnly,
            true,
        );

        $privacy = app(ElectionBallotVisibilityService::class);

        $this->assertTrue($privacy->canViewComment($event, $candidate));
        $this->assertNull($privacy->visibleCommentAuthorId($event, $candidate));
        $this->assertFalse($privacy->canViewComment($event, $member));
        $this->assertFalse($privacy->canViewComment($event, $official));
        $this->assertFalse($privacy->canViewComment($event, $outsider));
        $this->assertSame($voter->id, $privacy->visibleCommentAuthorId($event, $voter));
    }

    public function test_named_comment_author_visibility_is_independent_from_vote_identity_visibility(): void
    {
        [$event, $voter, $candidate, $member] = $this->fixture(
            ElectionVoteVisibility::Confidential,
            ElectionBallotCommentVisibility::AllMembers,
            false,
        );

        $privacy = app(ElectionBallotVisibilityService::class);

        $this->assertNull($privacy->visibleVoterId($event, $member));
        $this->assertTrue($privacy->canViewComment($event, $member));
        $this->assertSame($voter->id, $privacy->visibleCommentAuthorId($event, $member));
        $this->assertTrue($privacy->canViewComment($event, $candidate));
    }

    private function fixture(
        ElectionVoteVisibility $voteVisibility,
        ElectionBallotCommentVisibility $commentVisibility,
        bool $commentAnonymous,
    ): array {
        $group = Group::create([
            'name' => 'E0 privacy group',
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

        $voter = User::factory()->create();
        $candidate = User::factory()->create();
        $member = User::factory()->create();
        $official = User::factory()->create();
        $outsider = User::factory()->create();

        foreach ([$voter, $candidate, $member, $official] as $user) {
            GroupUser::create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role' => 1,
                'status' => 1,
            ]);
        }

        $contract = ElectionResponsibilityContractVersion::create([
            'position' => 'manager',
            'version' => 1,
            'body' => 'privacy test contract',
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);
        $offer = ElectionResponsibilityOffer::create([
            'election_id' => $election->id,
            'candidate_user_id' => $official->id,
            'position' => 'manager',
            'ranking_position' => 1,
            'contract_version_id' => $contract->id,
            'status' => 'accepted',
            'offered_at' => now()->subHours(2),
            'expires_at' => now()->addDays(7),
            'responded_at' => now()->subHour(),
            'eligibility_checked_at' => now()->subHours(2),
            'resolution_reason' => 'privacy_test_accepted',
        ]);
        ElectionAppointment::create([
            'election_id' => $election->id,
            'responsibility_offer_id' => $offer->id,
            'user_id' => $official->id,
            'group_id' => $group->id,
            'position' => 'manager',
            'group_role' => 2,
            'appointment_kind' => 'direct',
            'status' => 'active',
            'appointed_at' => now()->subHour(),
            'actor' => 'test',
            'reason' => 'e0_visibility_test',
        ]);

        $event = ElectionBallotEvent::create([
            'election_id' => $election->id,
            'voter_id' => $voter->id,
            'event_type' => 'vote_cast',
            'candidate_user_id' => $candidate->id,
            'position' => 'manager',
            'vote_visibility' => $voteVisibility,
            'comment' => 'بازخورد آزمایشی',
            'comment_visibility' => $commentVisibility,
            'comment_anonymous' => $commentAnonymous,
            'request_uuid' => 'e0-privacy-'.uniqid('', true),
            'occurred_at' => now(),
        ]);

        return [$event, $voter, $candidate, $member, $official, $outsider];
    }
}
