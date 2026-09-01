<?php

namespace Tests\Feature\Elections;

use App\Models\Election;
use App\Models\ElectionResponsibilityContractVersion;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\Elections\ElectionCycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionCycleLineageTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_cycle_links_to_previous_historical_cycle_with_monotonic_number(): void
    {
        $manifest = array_fill_keys(ElectionResponsibilityContractVersion::REQUIRED_CLAUSES, 'متن معتبر قرارداد تست lineage');
        ElectionResponsibilityContractVersion::create([
            'position' => 'manager',
            'version' => 1,
            'body' => 'manager E0 lineage fixture',
            'clause_manifest' => $manifest,
            'e0_compliant' => true,
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);

        $group = Group::create([
            'name' => 'Historical cycle lineage group',
            'group_type' => '0',
            'location_level' => 'global',
            'address_id' => null,
        ]);
        GroupSetting::create([
            'level' => 'global',
            'manager_count' => 1,
            'inspector_count' => 0,
            'election_time' => 10,
            'max_for_election' => 1,
            'election_status' => 1,
            'second_election_time' => 0,
        ]);
        $user = User::factory()->create(['is_system' => false]);
        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 1,
            'status' => 1,
        ]);

        $first = Election::create([
            'group_id' => $group->id,
            'cycle_number' => 1,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subWeeks(3),
            'is_closed' => true,
            'lifecycle_status' => 'cancelled',
        ]);

        $second = app(ElectionCycleService::class)->ensureForGroup($group);

        $this->assertNotNull($second);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, (int) $second->cycle_number);
        $this->assertSame($first->id, (int) $second->previous_election_id);
        $this->assertSame($first->id, $second->previousCycle->id);
        $this->assertSame($second->id, $first->refresh()->nextCycle->id);
        $this->assertSame(2, Election::where('group_id', $group->id)->count());
    }
}
