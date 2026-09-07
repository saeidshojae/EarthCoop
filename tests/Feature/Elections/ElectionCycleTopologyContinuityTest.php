<?php

namespace Tests\Feature\Elections;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Models\Election;
use App\Models\ElectionResponsibilityContractVersion;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\Elections\ElectionCycleService;
use App\Services\Elections\ElectionGroupHierarchyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ElectionCycleTopologyContinuityTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_structural_child_suppresses_duplicate_parent_election_regardless_of_population(): void
    {
        DB::table('neighborhoods')->insert([
            'id' => 9701,
            'name' => 'Neighborhood A',
            'parent_id' => 7001,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('streets')->insert([
            'id' => 9702,
            'name' => 'Only Street',
            'parent_id' => 9701,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $group = $this->group('neighborhood', 9701);
        $this->setting('neighborhood', 1);

        foreach (range(1, 5) as $_) {
            $this->addActiveMember($group);
        }

        $resolver = app(ElectionGroupHierarchyResolver::class);
        $this->assertSame(1, $resolver->effectiveStructuralChildCount($group));
        $this->assertFalse($resolver->isIndependentElectoralLayer($group));

        $result = app(ElectionCycleService::class)->ensureForGroup($group);

        $this->assertNull($result);
        $this->assertSame(0, Election::where('group_id', $group->id)->count());
    }

    public function test_second_structural_child_activates_real_parent_election_without_manual_override(): void
    {
        DB::table('neighborhoods')->insert([
            'id' => 9711,
            'name' => 'Neighborhood B',
            'parent_id' => 7002,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('streets')->insert([
            'id' => 9712,
            'name' => 'Street A',
            'parent_id' => 9711,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $group = $this->group('neighborhood', 9711);
        $this->setting('neighborhood', 1);
        $this->addActiveMember($group);

        $service = app(ElectionCycleService::class);
        $this->assertNull($service->ensureForGroup($group));

        DB::table('streets')->insert([
            'id' => 9713,
            'name' => 'Street B',
            'parent_id' => 9711,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resolver = app(ElectionGroupHierarchyResolver::class);
        $this->assertSame(2, $resolver->effectiveStructuralChildCount($group));
        $this->assertTrue($resolver->isIndependentElectoralLayer($group));

        $election = $service->ensureForGroup($group);

        $this->assertNotNull($election);
        $this->assertSame(ElectionLifecycleStatus::Open, $election->lifecycle_status);
        $this->assertSame(1, Election::where('group_id', $group->id)->count());
    }

    public function test_city_without_region_counts_direct_neighborhoods_as_effective_constituencies(): void
    {
        $this->urbanBase(9720);

        DB::table('neighborhoods')->insert([
            'id' => 9728,
            'name' => 'Direct Neighborhood A',
            'parent_id' => 9726,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $city = $this->group('city', 9726);
        $this->setting('city', 1);
        $this->addActiveMember($city);

        $resolver = app(ElectionGroupHierarchyResolver::class);
        $this->assertSame(1, $resolver->effectiveStructuralChildCount($city));
        $this->assertNull(app(ElectionCycleService::class)->ensureForGroup($city));

        DB::table('neighborhoods')->insert([
            'id' => 9729,
            'name' => 'Direct Neighborhood B',
            'parent_id' => 9726,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(2, $resolver->effectiveStructuralChildCount($city));
        $this->assertNotNull(app(ElectionCycleService::class)->ensureForGroup($city));
    }

    public function test_pending_geographic_child_does_not_change_electoral_topology(): void
    {
        DB::table('neighborhoods')->insert([
            'id' => 9731,
            'name' => 'Neighborhood C',
            'parent_id' => 7003,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('streets')->insert([
            [
                'id' => 9732,
                'name' => 'Approved Street',
                'parent_id' => 9731,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 9733,
                'name' => 'Pending Street',
                'parent_id' => 9731,
                'status' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $group = $this->group('neighborhood', 9731);
        $this->setting('neighborhood', 1);
        $this->addActiveMember($group);

        $resolver = app(ElectionGroupHierarchyResolver::class);
        $this->assertSame(1, $resolver->effectiveStructuralChildCount($group));
        $this->assertNull(app(ElectionCycleService::class)->ensureForGroup($group));
    }

    public function test_application_phase_opens_successor_ballot_window_instead_of_blocking_voting(): void
    {
        DB::table('neighborhoods')->insert([
            'id' => 9741, 'name' => 'Neighborhood D', 'parent_id' => 7004, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $group = $this->group('neighborhood', 9741);
        $this->setting('neighborhood', 1);
        $this->addActiveMember($group);

        $existing = Election::create([
            'group_id' => $group->id,
            'cycle_number' => 1,
            'starts_at' => now()->subDays(20),
            'ends_at' => now()->subDays(10),
            'is_closed' => true,
            'lifecycle_status' => ElectionLifecycleStatus::AwaitingAcceptance,
        ]);

        $next = app(ElectionCycleService::class)->ensureForGroup($group);

        $this->assertNotNull($next);
        $this->assertNotSame($existing->id, $next->id);
        $this->assertSame(ElectionLifecycleStatus::Open, $next->lifecycle_status);
        $this->assertSame($existing->id, (int) $next->previous_election_id);
        $this->assertSame(2, (int) $next->cycle_number);
        $this->assertSame(2, Election::where('group_id', $group->id)->count());
        $this->assertTrue($next->ends_at->greaterThan(now()->addMonths(2)));
    }

    public function test_filled_cycle_opens_next_ballot_window_immediately_instead_of_waiting_silently(): void
    {
        DB::table('neighborhoods')->insert([
            'id' => 9751, 'name' => 'Neighborhood E', 'parent_id' => 7005, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $group = $this->group('neighborhood', 9751);
        $this->setting('neighborhood', 1);
        $this->addActiveMember($group);

        $filled = Election::create([
            'group_id' => $group->id,
            'cycle_number' => 1,
            'starts_at' => now()->subMonths(2)->subDays(10),
            'ends_at' => now()->subMonths(2),
            'is_closed' => true,
            'lifecycle_status' => ElectionLifecycleStatus::Filled,
        ]);
        $filled->lifecycleTransitions()->create([
            'from_status' => ElectionLifecycleStatus::Appointing,
            'to_status' => ElectionLifecycleStatus::Filled,
            'reason' => 'test_term_filled',
            'source' => 'test',
            'transitioned_at' => now()->subMonths(2),
        ]);

        $service = app(ElectionCycleService::class);
        $next = $service->ensureForGroup($group);

        $this->assertNotNull($next);
        $this->assertNotSame($filled->id, $next->id);
        $this->assertSame(ElectionLifecycleStatus::Open, $next->lifecycle_status);
        $this->assertSame($filled->id, (int) $next->previous_election_id);
        $this->assertSame(2, Election::where('group_id', $group->id)->count());

        $retry = $service->ensureForGroup($group);
        $this->assertSame($next->id, $retry?->id);
        $this->assertSame(2, Election::where('group_id', $group->id)->count());
    }

    public function test_cancelled_cycle_can_be_replaced_immediately(): void
    {
        DB::table('neighborhoods')->insert([
            'id' => 9761, 'name' => 'Neighborhood F', 'parent_id' => 7006, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $group = $this->group('neighborhood', 9761);
        $this->setting('neighborhood', 1);
        $this->addActiveMember($group);

        $cancelled = Election::create([
            'group_id' => $group->id,
            'cycle_number' => 1,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(9),
            'is_closed' => true,
            'lifecycle_status' => ElectionLifecycleStatus::Cancelled,
        ]);

        $next = app(ElectionCycleService::class)->ensureForGroup($group);

        $this->assertNotNull($next);
        $this->assertNotSame($cancelled->id, $next->id);
        $this->assertSame(2, Election::where('group_id', $group->id)->count());
    }

    private function group(string $level, int $addressId): Group
    {
        return Group::create([
            'name' => 'E9 '.$level.' '.$addressId,
            'group_type' => '0',
            'location_level' => $level,
            'address_id' => $addressId,
        ]);
    }

    private function setting(string $level, int $threshold): GroupSetting
    {
        $this->ensureContracts();

        return GroupSetting::create([
            'level' => $level,
            'inspector_count' => 3,
            'manager_count' => 7,
            'election_time' => 10,
            'max_for_election' => $threshold,
            'election_status' => 1,
            'second_election_time' => 3,
        ]);
    }

    private function ensureContracts(): void
    {
        $manifest = array_fill_keys(ElectionResponsibilityContractVersion::REQUIRED_CLAUSES, 'متن معتبر قرارداد تست topology');
        foreach (['manager', 'inspector'] as $position) {
            ElectionResponsibilityContractVersion::query()->firstOrCreate(
                ['position' => $position, 'version' => 1],
                [
                    'body' => "{$position} E0 topology fixture",
                    'clause_manifest' => $manifest,
                    'e0_compliant' => true,
                    'is_active' => true,
                    'published_at' => now()->subDay(),
                ],
            );
        }
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

    private function urbanBase(int $base): void
    {
        DB::table('continents')->insert([
            'id' => $base + 1, 'name' => 'Continent E9', 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('countries')->insert([
            'id' => $base + 2, 'name' => 'Country E9', 'continent_id' => $base + 1, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('provinces')->insert([
            'id' => $base + 3, 'name' => 'Province E9', 'country_id' => $base + 2, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('counties')->insert([
            'id' => $base + 4, 'name' => 'County E9', 'province_id' => $base + 3,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('districts')->insert([
            'id' => $base + 5, 'name' => 'Section E9', 'province_id' => $base + 3,
            'county_id' => $base + 4, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('cities')->insert([
            'id' => $base + 6, 'name' => 'City E9', 'province_id' => $base + 3,
            'county_id' => $base + 4, 'district_id' => $base + 5, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
