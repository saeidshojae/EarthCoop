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
use App\Services\Elections\ElectionResponsibilityContractVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ElectionProcessReviewGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_representation_is_a_supported_e0_review_ground(): void
    {
        [$election, $member] = $this->basicElection();

        $review = app(ElectionProcessReviewService::class)->openAutomaticReview(
            $election,
            $member,
            'representation',
            'appointment_representation',
            now()->subHour(),
            null,
            $member->id,
        );

        $this->assertSame('representation', $review->ground);
        $this->assertTrue(in_array('representation', ElectionProcessReview::GROUNDS, true));
        $this->assertSame('discrepancy', $review->automatic_status);
    }

    public function test_human_review_request_expires_seven_days_after_challenged_event(): void
    {
        [$election, $member] = $this->basicElection();
        $review = ElectionProcessReview::create([
            'election_id' => $election->id,
            'requester_user_id' => $member->id,
            'subject_user_id' => $member->id,
            'ground' => 'representation',
            'challenged_event' => 'appointment_representation',
            'event_occurred_at' => now()->subDays(8),
            'automatic_status' => 'requires_human_review',
            'automatic_result' => ['identity_disclosure' => 'none'],
            'human_status' => 'not_requested',
            'support_count' => 0,
            'human_deadline_at' => now()->subDay(),
        ]);

        try {
            app(ElectionProcessReviewService::class)->requestHumanReview($review, $member);
            $this->fail('Expired E0 review request must fail closed.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('seven-day', $exception->getMessage());
        }

        $this->assertSame('expired', $review->refresh()->human_status);
    }

    public function test_pending_review_gets_fourteen_day_sla_and_reasoned_stay_and_correction_are_audited(): void
    {
        [$election, $member, $group] = $this->basicElection();
        $appointment = $this->appointment($election, $group, $member);
        $review = ElectionProcessReview::create([
            'election_id' => $election->id,
            'requester_user_id' => $member->id,
            'subject_user_id' => $member->id,
            'appointment_id' => $appointment->id,
            'ground' => 'representation',
            'challenged_event' => 'appointment_representation',
            'event_occurred_at' => now()->subDay(),
            'automatic_status' => 'requires_human_review',
            'automatic_result' => ['identity_disclosure' => 'none'],
            'human_status' => 'not_requested',
            'support_count' => 0,
            'human_deadline_at' => now()->addDays(6),
        ]);

        $service = app(ElectionProcessReviewService::class);
        $review = $service->requestHumanReview($review, $member);

        $this->assertSame('pending', $review->human_status);
        $this->assertEquals(14, now()->startOfDay()->diffInDays($review->decision_due_at->copy()->startOfDay()));
        $this->assertSame('provisional', $appointment->refresh()->review_state);

        $authority = User::factory()->create();
        $review = $service->setInterimStay($review, $authority, 'خطر اثرگذاری غیرقابل بازگشت تا پایان بازبینی');
        $this->assertSame('stayed', $review->interim_state);
        $this->assertSame('stayed', $appointment->refresh()->review_state);
        $this->assertDatabaseHas('election_review_audit_accesses', [
            'review_id' => $review->id,
            'actor_user_id' => $authority->id,
            'purpose' => 'interim_stay_decision',
        ]);

        try {
            $service->decide($review, $authority, 'corrected', 'اصلاح لازم است');
            $this->fail('Corrected decision without remediation reference must fail.');
        } catch (\InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $review = $service->decide(
            $review->refresh(),
            $authority,
            'corrected',
            'نمایندگی ثبت‌شده با داده مرجع تطبیق نداشت و اصلاح شد.',
            'representation-fix:case-1',
        );
        $this->assertSame('decided', $review->human_status);
        $this->assertSame('corrected', $review->decision);
        $this->assertSame('clear', $appointment->refresh()->review_state);
        $this->assertDatabaseHas('election_review_audit_accesses', [
            'review_id' => $review->id,
            'actor_user_id' => $authority->id,
            'purpose' => 'reasoned_final_decision',
        ]);
    }

    private function basicElection(): array
    {
        $group = Group::create([
            'name' => 'E0 review governance group',
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
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonth(),
            'is_closed' => false,
            'lifecycle_status' => 'open',
        ]);

        return [$election, $member, $group];
    }

    private function appointment(Election $election, Group $group, User $member): ElectionAppointment
    {
        $clauses = array_fill_keys(
            ElectionResponsibilityContractVersion::REQUIRED_CLAUSES,
            'متن کامل قرارداد آزمون بازبینی نمایندگی'
        );
        $contract = app(ElectionResponsibilityContractVersionService::class)
            ->publish('manager', $clauses, $member, 'review governance test');

        $offer = ElectionResponsibilityOffer::create([
            'election_id' => $election->id,
            'candidate_user_id' => $member->id,
            'position' => 'manager',
            'ranking_position' => 1,
            'contract_version_id' => $contract->id,
            'status' => 'accepted',
            'offered_at' => now()->subDays(2),
            'expires_at' => now()->addDays(5),
            'responded_at' => now()->subDay(),
            'response_metadata' => [
                'acceptance_evidence' => [
                    'candidate_user_id' => $member->id,
                    'contract_version_id' => $contract->id,
                    'confirmed_at' => now()->subDay()->toISOString(),
                ],
            ],
        ]);

        return ElectionAppointment::create([
            'election_id' => $election->id,
            'responsibility_offer_id' => $offer->id,
            'user_id' => $member->id,
            'group_id' => $group->id,
            'position' => 'manager',
            'group_role' => 2,
            'appointment_kind' => 'direct',
            'status' => 'active',
            'appointed_at' => now()->subDay(),
            'actor' => 'test',
            'reason' => 'review governance fixture',
        ]);
    }
}
