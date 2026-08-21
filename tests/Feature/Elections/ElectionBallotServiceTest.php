<?php

namespace Tests\Feature\Elections;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Models\Election;
use App\Models\ElectionBallotEvent;
use App\Models\ElectionEligibilitySnapshot;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\User;
use App\Models\Vote;
use App\Services\Elections\ElectionBallotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ElectionBallotServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ballot_projection_changes_without_losing_audit_history(): void
    {
        [$election, $voter, $manager, $inspector] = $this->fixture();
        $service = app(ElectionBallotService::class);

        $service->submit($election, $voter->id, [$manager->id], [$inspector->id], 'req-1');

        $this->assertDatabaseHas('votes', [
            'election_id' => $election->id,
            'voter_id' => $voter->id,
            'candidate_user_id' => $manager->id,
            'position' => '1',
        ]);
        $this->assertDatabaseHas('votes', [
            'election_id' => $election->id,
            'voter_id' => $voter->id,
            'candidate_user_id' => $inspector->id,
            'position' => '0',
        ]);
        $this->assertSame(2, ElectionBallotEvent::where('request_uuid', 'req-1')->where('event_type', 'cast')->count());

        $service->submit($election, $voter->id, [], [$manager->id], 'req-2');

        $this->assertSame(1, Vote::where('election_id', $election->id)->where('voter_id', $voter->id)->count());
        $this->assertDatabaseHas('votes', [
            'candidate_user_id' => $manager->id,
            'position' => '0',
        ]);
        $this->assertDatabaseHas('election_ballot_events', [
            'request_uuid' => 'req-2',
            'event_type' => 'changed',
            'candidate_user_id' => $manager->id,
            'previous_candidate_user_id' => $manager->id,
            'position' => 'inspector',
            'previous_position' => 'manager',
        ]);
        $this->assertDatabaseHas('election_ballot_events', [
            'request_uuid' => 'req-2',
            'event_type' => 'withdrawn',
            'previous_candidate_user_id' => $inspector->id,
            'previous_position' => 'inspector',
        ]);
        $this->assertSame(4, ElectionBallotEvent::where('election_id', $election->id)->count());
    }

    public function test_ballot_rejects_voter_or_candidate_outside_frozen_snapshot(): void
    {
        [$election, $voter, $manager] = $this->fixture();
        $outsider = User::factory()->create();
        $service = app(ElectionBallotService::class);

        try {
            $service->submit($election, $outsider->id, [$manager->id], [], 'req-voter');
            $this->fail('Expected voter eligibility validation failure.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('voter', $e->errors());
        }

        try {
            $service->submit($election, $voter->id, [$outsider->id], [], 'req-candidate');
            $this->fail('Expected selectable eligibility validation failure.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('ballot', $e->errors());
        }

        $this->assertSame(0, Vote::where('election_id', $election->id)->count());
        $this->assertSame(0, ElectionBallotEvent::where('election_id', $election->id)->count());
    }

    public function test_ballot_enforces_policy_capacity_and_cross_role_uniqueness(): void
    {
        [$election, $voter, $manager] = $this->fixture(managerCount: 1, inspectorCount: 1);
        $secondManager = User::factory()->create();
        $this->snapshot($election, $secondManager, true, true);
        $service = app(ElectionBallotService::class);

        try {
            $service->submit($election, $voter->id, [$manager->id, $secondManager->id], [], 'req-capacity');
            $this->fail('Expected manager capacity validation failure.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('manager', $e->errors());
        }

        try {
            $service->submit($election, $voter->id, [$manager->id], [$manager->id], 'req-duplicate');
            $this->fail('Expected cross-role duplicate validation failure.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('ballot', $e->errors());
        }
    }

    public function test_ballot_fails_closed_when_existing_legacy_vote_identity_is_unresolved(): void
    {
        [$election, $voter, $manager] = $this->fixture();
        Vote::create([
            'election_id' => $election->id,
            'voter_id' => $voter->id,
            'candidate_id' => 999999,
            'candidate_user_id' => null,
            'position' => 1,
        ]);

        $service = app(ElectionBallotService::class);

        try {
            $service->submit($election, $voter->id, [$manager->id], [], 'req-unresolved');
            $this->fail('Expected unresolved legacy ballot validation failure.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('ballot', $e->errors());
        }

        $this->assertDatabaseHas('votes', [
            'election_id' => $election->id,
            'voter_id' => $voter->id,
            'candidate_id' => 999999,
            'candidate_user_id' => null,
        ]);
        $this->assertSame(0, ElectionBallotEvent::where('election_id', $election->id)->count());
    }

    public function test_ballot_rejects_oversized_idempotency_key_without_mutating_projection(): void
    {
        [$election, $voter, $manager] = $this->fixture();
        $service = app(ElectionBallotService::class);

        try {
            $service->submit($election, $voter->id, [$manager->id], [], str_repeat('x', 97));
            $this->fail('Expected idempotency key validation failure.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('idempotency_key', $e->errors());
        }

        $this->assertSame(0, Vote::where('election_id', $election->id)->where('voter_id', $voter->id)->count());
        $this->assertSame(0, ElectionBallotEvent::where('election_id', $election->id)->count());
    }

    private function fixture(int $managerCount = 2, int $inspectorCount = 2): array
    {
        $group = Group::create([
            'name' => 'Ballot v2 test group',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
        ]);

        GroupSetting::create([
            'level' => 'neighborhood',
            'manager_count' => $managerCount,
            'inspector_count' => $inspectorCount,
            'election_time' => 10,
            'max_for_election' => 1,
            'election_status' => 1,
        ]);

        $election = Election::create([
            'group_id' => $group->id,
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addDays(10),
            'is_closed' => false,
            'lifecycle_status' => ElectionLifecycleStatus::Open,
            'eligibility_snapshot_captured_at' => now(),
            'eligibility_snapshot_version' => 1,
        ]);

        $voter = User::factory()->create();
        $manager = User::factory()->create();
        $inspector = User::factory()->create();

        $this->snapshot($election, $voter, true, true);
        $this->snapshot($election, $manager, true, true);
        $this->snapshot($election, $inspector, true, true);

        return [$election, $voter, $manager, $inspector];
    }

    private function snapshot(Election $election, User $user, bool $voterEligible, bool $selectableEligible): void
    {
        ElectionEligibilitySnapshot::create([
            'election_id' => $election->id,
            'user_id' => $user->id,
            'voter_eligible' => $voterEligible,
            'selectable_eligible' => $selectableEligible,
            'membership_role' => 1,
            'membership_status' => 1,
            'snapshot_version' => 1,
            'captured_at' => now(),
        ]);
    }
}
