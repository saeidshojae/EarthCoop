<?php

namespace Tests\Feature\Elections;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\ElectionLifecycleTransition;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\Elections\ElectionCycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionCycleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cycle_is_not_created_before_policy_threshold(): void
    {
        [$group] = $this->configuredGroup(2);
        $this->addActiveMember($group);

        $result = app(ElectionCycleService::class)->ensureForGroup($group);

        $this->assertNull($result);
        $this->assertSame(0, Election::where('group_id', $group->id)->count());
    }

    public function test_threshold_reached_creates_and_opens_exactly_one_cycle(): void
    {
        [$group] = $this->configuredGroup(2);
        $first = $this->addActiveMember($group);
        $second = $this->addActiveMember($group);

        $service = app(ElectionCycleService::class);
        $election = $service->ensureForGroup($group);

        $this->assertNotNull($election);
        $this->assertSame(ElectionLifecycleStatus::Open, $election->lifecycle_status);
        $this->assertFalse((bool) $election->is_closed);
        $this->assertSame(1, Election::where('group_id', $group->id)->count());
        $this->assertSame(2, Candidate::where('election_id', $election->id)->count());
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            Candidate::where('election_id', $election->id)->pluck('user_id')->map(fn ($id) => (int) $id)->all(),
        );
        $this->assertDatabaseHas('election_lifecycle_transitions', [
            'election_id' => $election->id,
            'from_status' => 'scheduled',
            'to_status' => 'open',
            'reason' => 'policy_threshold_reached',
            'source' => 'scheduler',
        ]);

        $retry = $service->ensureForGroup($group);

        $this->assertSame($election->id, $retry?->id);
        $this->assertSame(1, Election::where('group_id', $group->id)->count());
        $this->assertSame(2, Candidate::where('election_id', $election->id)->count());
        $this->assertSame(1, ElectionLifecycleTransition::where('election_id', $election->id)->count());
    }

    public function test_disabled_policy_never_creates_cycle_even_above_threshold(): void
    {
        [$group, $setting] = $this->configuredGroup(1);
        $setting->update(['election_status' => 0]);
        $this->addActiveMember($group);

        $result = app(ElectionCycleService::class)->ensureForGroup($group);

        $this->assertNull($result);
        $this->assertSame(0, Election::where('group_id', $group->id)->count());
    }

    private function configuredGroup(int $threshold): array
    {
        $group = Group::create([
            'name' => 'Automatic election cycle test group',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
        ]);

        $setting = GroupSetting::create([
            'level' => 'neighborhood',
            'inspector_count' => 3,
            'manager_count' => 7,
            'election_time' => 10,
            'max_for_election' => $threshold,
            'election_status' => 1,
            'second_election_time' => 3,
        ]);

        return [$group, $setting];
    }

    private function addActiveMember(Group $group): User
    {
        $user = User::factory()->create(['is_system' => false]);
        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 1,
            'status' => 1,
        ]);

        return $user;
    }
}
