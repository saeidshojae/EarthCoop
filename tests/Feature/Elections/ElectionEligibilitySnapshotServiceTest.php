<?php

namespace Tests\Feature\Elections;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Models\Election;
use App\Models\ElectionEligibilitySnapshot;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\Elections\ElectionEligibilitySnapshotService;
use App\Services\Elections\ElectionLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionEligibilitySnapshotServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_transition_freezes_voters_and_selectable_members_with_exclusion_reasons(): void
    {
        $group = $this->group();
        $active = $this->member($group, 1, 1, false);
        $observer = $this->member($group, 0, 1, false);
        $guest = $this->member($group, 4, 1, false);
        $inactive = $this->member($group, 1, 0, false);
        $system = $this->member($group, 1, 1, true);
        $election = $this->scheduledElection($group);
        $membershipCountAtCapture = GroupUser::where('group_id', $group->id)->count();

        $opened = app(ElectionLifecycleService::class)->transition(
            $election,
            ElectionLifecycleStatus::Open,
            'test_open',
            'test',
        );

        $this->assertNotNull($opened->eligibility_snapshot_captured_at);
        $this->assertSame(ElectionEligibilitySnapshotService::VERSION, $opened->eligibility_snapshot_version);
        $this->assertSame(
            $membershipCountAtCapture,
            ElectionEligibilitySnapshot::where('election_id', $election->id)->count(),
            'Every membership existing at capture time must receive exactly one historical eligibility row.'
        );

        $this->assertDatabaseHas('election_eligibility_snapshots', [
            'election_id' => $election->id,
            'user_id' => $active->id,
            'voter_eligible' => 1,
            'selectable_eligible' => 1,
            'voter_exclusion_reason' => null,
        ]);
        $this->assertDatabaseHas('election_eligibility_snapshots', [
            'election_id' => $election->id,
            'user_id' => $observer->id,
            'voter_eligible' => 0,
            'voter_exclusion_reason' => 'observer_role',
        ]);
        $this->assertDatabaseHas('election_eligibility_snapshots', [
            'election_id' => $election->id,
            'user_id' => $guest->id,
            'voter_eligible' => 0,
            'voter_exclusion_reason' => 'guest_role',
        ]);
        $this->assertDatabaseHas('election_eligibility_snapshots', [
            'election_id' => $election->id,
            'user_id' => $inactive->id,
            'voter_eligible' => 0,
            'voter_exclusion_reason' => 'inactive_membership',
        ]);
        $this->assertDatabaseHas('election_eligibility_snapshots', [
            'election_id' => $election->id,
            'user_id' => $system->id,
            'voter_eligible' => 0,
            'voter_exclusion_reason' => 'system_user',
        ]);
    }

    public function test_snapshot_is_immutable_when_membership_changes_after_open(): void
    {
        $group = $this->group();
        $eligible = $this->member($group, 1, 1, false);
        $observer = $this->member($group, 0, 1, false);
        $election = $this->scheduledElection($group);
        $lifecycle = app(ElectionLifecycleService::class);
        $snapshots = app(ElectionEligibilitySnapshotService::class);
        $membershipCountAtCapture = GroupUser::where('group_id', $group->id)->count();

        $opened = $lifecycle->transition($election, ElectionLifecycleStatus::Open, 'test_open', 'test');
        $this->assertSame([$eligible->id], $snapshots->voterIds($opened));

        GroupUser::where('group_id', $group->id)->where('user_id', $eligible->id)->update(['status' => 0]);
        GroupUser::where('group_id', $group->id)->where('user_id', $observer->id)->update(['role' => 1]);

        // Retrying the already-applied open transition is a no-op and must not
        // recapture eligibility from the changed membership projection.
        $lifecycle->transition($opened->fresh(), ElectionLifecycleStatus::Open, 'retry_open', 'test');

        $this->assertSame([$eligible->id], $snapshots->voterIds($opened));
        $this->assertSame([$eligible->id], $snapshots->selectableUserIds($opened));
        $this->assertSame(
            $membershipCountAtCapture,
            ElectionEligibilitySnapshot::where('election_id', $election->id)->count(),
            'Snapshot row count must remain frozen even when membership roles/statuses change later.'
        );
    }

    private function group(): Group
    {
        return Group::create([
            'name' => 'Eligibility snapshot test group',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
        ]);
    }

    private function member(Group $group, int $role, int $status, bool $system): User
    {
        $user = User::factory()->create(['is_system' => $system]);
        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => $status,
        ]);

        return $user;
    }

    private function scheduledElection(Group $group): Election
    {
        return Election::create([
            'group_id' => $group->id,
            'starts_at' => now(),
            'ends_at' => now()->addDays(10),
            'is_closed' => false,
            'lifecycle_status' => ElectionLifecycleStatus::Scheduled,
        ]);
    }
}
