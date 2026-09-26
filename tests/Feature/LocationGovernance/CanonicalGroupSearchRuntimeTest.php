<?php

namespace Tests\Feature\LocationGovernance;

use App\Http\Controllers\Group\CanonicalGroupSearchController;
use App\Models\GovernanceArea;
use App\Models\Group;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class CanonicalGroupSearchRuntimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'location-governance.groups_enabled' => true,
            'location-governance.elections_enabled' => true,
        ]);
    }

    public function test_search_uses_canonical_level_and_supports_guest_and_temporary_active_roles(): void
    {
        $area = GovernanceArea::factory()->official()->create([
            'governance_type' => 'urban_region',
            'status' => 'active',
        ]);
        $guest = User::factory()->create();
        $temporary = User::factory()->create();

        $guestGroup = Group::query()->create([
            'name' => 'Canonical guest group',
            'group_type' => '0',
            'governance_area_id' => $area->id,
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
            'location_level' => 'province',
        ]);
        $guest->groups()->attach($guestGroup->id, ['role' => 4, 'status' => 1]);

        $temporaryGroup = Group::query()->create([
            'name' => 'Canonical temporary group',
            'group_type' => '0',
            'governance_area_id' => $area->id,
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
            'location_level' => 'province',
        ]);
        $temporary->groups()->attach($temporaryGroup->id, ['role' => 5, 'status' => 1]);

        $guestPayload = $this->actingAs($guest)
            ->getJson('/api/groups/search?q=Canonical+guest&type=name')
            ->assertOk()
            ->json('groups.0');
        $this->assertSame('region', $guestPayload['location_level']);
        $this->assertSame('مهمان', $guestPayload['role']);

        $temporaryPayload = $this->actingAs($temporary)
            ->getJson('/api/groups/search?q=Canonical+temporary&type=name')
            ->assertOk()
            ->json('groups.0');
        $this->assertSame('region', $temporaryPayload['location_level']);
        $this->assertSame('فعال موقت', $temporaryPayload['role']);
    }

    public function test_search_excludes_inactive_memberships(): void
    {
        $user = User::factory()->create();
        $group = Group::query()->create([
            'name' => 'Inactive membership target',
            'group_type' => '0',
            'location_level' => 'city',
        ]);
        $user->groups()->attach($group->id, ['role' => 1, 'status' => 0]);

        $this->actingAs($user)
            ->getJson('/api/groups/search?q=Inactive&type=name')
            ->assertOk()
            ->assertExactJson(['groups' => []]);
    }

    public function test_content_search_never_escapes_callers_active_membership_scope(): void
    {
        $user = User::factory()->create();
        $memberGroup = Group::query()->create([
            'name' => 'Member group',
            'group_type' => '0',
            'location_level' => 'city',
        ]);
        $outsideGroup = Group::query()->create([
            'name' => 'Outside group',
            'group_type' => '0',
            'location_level' => 'city',
        ]);
        $user->groups()->attach($memberGroup->id, ['role' => 1, 'status' => 1]);

        Poll::query()->create([
            'group_id' => $outsideGroup->id,
            'created_by' => User::factory()->create()->id,
            'question' => 'scope-leak-needle',
        ]);

        $this->actingAs($user)
            ->getJson('/api/groups/search?q=scope-leak-needle&type=content')
            ->assertOk()
            ->assertExactJson(['groups' => []]);
    }

    public function test_managed_group_keeps_its_active_pivot_role(): void
    {
        $user = User::factory()->create();
        $group = Group::query()->create([
            'name' => 'Managed active group',
            'group_type' => '0',
            'location_level' => 10,
        ]);
        $user->groups()->attach($group->id, ['role' => 1, 'status' => 1]);

        $payload = $this->actingAs($user)
            ->getJson('/api/groups/search?q=Managed&type=name')
            ->assertOk()
            ->json('groups.0');

        $this->assertSame('فعال', $payload['role']);
    }

    public function test_legacy_level_fallback_is_preserved_when_canonical_flags_are_disabled(): void
    {
        config([
            'location-governance.groups_enabled' => false,
            'location-governance.elections_enabled' => false,
        ]);

        $user = User::factory()->create();
        $group = Group::query()->create([
            'name' => 'Legacy fallback group',
            'group_type' => '0',
            'location_level' => 'street',
        ]);
        $user->groups()->attach($group->id, ['role' => 1, 'status' => 1]);

        $payload = $this->actingAs($user)
            ->getJson('/api/groups/search?q=Legacy&type=name')
            ->assertOk()
            ->json('groups.0');

        $this->assertSame('street', $payload['location_level']);
        $this->assertSame('فعال', $payload['role']);
    }

    public function test_unauthenticated_search_preserves_json_401_contract(): void
    {
        $this->getJson('/api/groups/search?q=test&type=name')
            ->assertStatus(401)
            ->assertExactJson(['error' => 'Unauthorized']);
    }

    public function test_runtime_route_resolves_to_canonical_search_controller(): void
    {
        $route = Route::getRoutes()->match(\Illuminate\Http\Request::create('/api/groups/search', 'GET'));

        $this->assertSame(CanonicalGroupSearchController::class, $route->getActionName());
    }
}
