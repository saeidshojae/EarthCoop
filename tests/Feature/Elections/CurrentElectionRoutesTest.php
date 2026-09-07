<?php

namespace Tests\Feature\Elections;

use App\Models\Election;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentElectionRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_current_election_route_uses_current_center_snapshot_contract(): void
    {
        [$user] = $this->memberWithGroup();

        $this->actingAs($user)
            ->get(route('history.election'))
            ->assertOk()
            ->assertViewIs('history.election')
            ->assertViewHas('snapshot')
            ->assertViewMissing('currentElections');
    }

    public function test_election_history_route_preserves_all_related_systemic_cycles(): void
    {
        [$user, $group] = $this->memberWithGroup();
        $open = $this->election($group, 'open', 1);
        $filled = $this->election($group, 'filled', 2);

        $response = $this->actingAs($user)->get(route('history.election-history'));

        $response->assertOk()
            ->assertViewIs('history.election-history')
            ->assertViewHas('currentElections', function ($elections) use ($open, $filled): bool {
                $ids = $elections->pluck('id')->all();
                return in_array($open->id, $ids, true) && in_array($filled->id, $ids, true);
            });
    }

    private function memberWithGroup(): array
    {
        $group = Group::create([
            'name' => 'History route group',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
            'is_open' => true,
        ]);
        $user = User::factory()->create(['is_system' => false]);
        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 1,
            'status' => 1,
        ]);

        return [$user, $group];
    }

    private function election(Group $group, string $status, int $cycle): Election
    {
        return Election::create([
            'group_id' => $group->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_closed' => ! in_array($status, ['scheduled', 'open'], true),
            'lifecycle_status' => $status,
            'cycle_number' => $cycle,
        ]);
    }
}
