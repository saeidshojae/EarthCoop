<?php

namespace Tests\Feature\Elections;

use App\Models\Election;
use App\Models\ElectionAppointment;
use App\Models\ElectionProcessReview;
use App\Models\ElectionResponsibilityContractVersion;
use App\Models\ElectionResponsibilityOffer;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\Elections\ElectionProcessReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionProcessReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_active_members_can_escalate_unresolved_review_and_appointment_becomes_provisional(): void
    {
        $group = Group::create(['name' => 'review group', 'group_type' => 'public', 'location_level' => 'neighborhood']);
        $election = Election::create([
            'group_id' => $group->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
            'is_closed' => false,
            'lifecycle_status' => 'open',
        ]);
        $subject = User::factory()->create();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $subject->id, 'role' => 1, 'status' => 1]);
        $contract = ElectionResponsibilityContractVersion::create([
            'position' => 'manager',
            'version' => 1,
            'body' => 'review test responsibility contract',
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);
        $offer = ElectionResponsibilityOffer::create([
            'election_id' => $election->id,
            'candidate_user_id' => $subject->id,
            'position' => 'manager',
            'ranking_position' => 1,
            'contract_version_id' => $contract->id,
            'status' => 'accepted',
            'offered_at' => now()->subHours(2),
            'expires_at' => now()->addDays(7),
            'responded_at' => now()->subHour(),
        ]);
        $appointment = ElectionAppointment::create([
            'election_id' => $election->id,
            'responsibility_offer_id' => $offer->id,
            'user_id' => $subject->id,
            'group_id' => $group->id,
            'position' => 'manager',
            'group_role' => 2,
            'appointment_kind' => 'direct',
            'status' => 'active',
            'appointed_at' => now(),
            'actor' => 'test',
            'reason' => 'test',
        ]);

        $members = collect(range(1, 3))->map(fn () => User::factory()->create());
        foreach ($members as $member) {
            GroupUser::create(['group_id' => $group->id, 'user_id' => $member->id, 'role' => 1, 'status' => 1]);
        }

        $review = ElectionProcessReview::create([
            'election_id' => $election->id,
            'requester_user_id' => $members[0]->id,
            'subject_user_id' => $subject->id,
            'appointment_id' => $appointment->id,
            'ground' => 'conflict_policy',
            'challenged_event' => 'appointment',
            'event_occurred_at' => now()->subDay(),
            'automatic_status' => 'requires_human_review',
            'automatic_result' => ['identity_disclosure' => 'none'],
            'human_status' => 'not_requested',
            'human_deadline_at' => now()->addDays(6),
        ]);

        $service = app(ElectionProcessReviewService::class);
        $service->requestHumanReview($review, $members[0]);
        $service->endorse($review, $members[1]);
        $review = $service->endorse($review, $members[2]);

        $this->assertSame('pending', $review->human_status);
        $this->assertSame(3, (int) $review->support_count);
        $this->assertNotNull($review->decision_due_at);
        $this->assertSame('provisional', $appointment->refresh()->review_state);
    }

    public function test_interested_candidate_can_escalate_without_three_member_threshold(): void
    {
        $group = Group::create(['name' => 'candidate review group', 'group_type' => 'public', 'location_level' => 'neighborhood']);
        $election = Election::create([
            'group_id' => $group->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
            'is_closed' => false,
            'lifecycle_status' => 'open',
        ]);
        $candidate = User::factory()->create();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $candidate->id, 'role' => 1, 'status' => 1]);
        $review = ElectionProcessReview::create([
            'election_id' => $election->id,
            'requester_user_id' => $candidate->id,
            'subject_user_id' => $candidate->id,
            'ground' => 'membership_eligibility',
            'challenged_event' => 'eligibility_snapshot',
            'event_occurred_at' => now()->subDay(),
            'automatic_status' => 'requires_human_review',
            'automatic_result' => ['identity_disclosure' => 'none'],
            'human_status' => 'not_requested',
            'human_deadline_at' => now()->addDays(6),
        ]);

        $review = app(ElectionProcessReviewService::class)->requestHumanReview($review, $candidate);
        $this->assertSame('pending', $review->human_status);
        $this->assertSame(0, (int) $review->support_count);
    }
}
