<?php

namespace Tests\Feature\LocationGovernance;

use App\Http\Controllers\Group\GroupController;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\MembershipFixture;
use Tests\TestCase;

class CanonicalGroupIndexCutoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_groups_index_resolves_canonical_memberships_when_groups_cutover_is_enabled(): void
    {
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
            'location-governance.groups_enabled' => true,
        ]);

        ['user' => $user, 'area' => $area] = MembershipFixture::canonicalUser();

        $legacyGroup = Group::query()->create([
            'name' => 'Legacy spatial public group',
            'group_type' => '0',
            'location_level' => 'city',
            'is_open' => 1,
        ]);
        $user->groups()->attach($legacyGroup->id, [
            'role' => 1,
            'status' => 1,
        ]);

        $this->actingAs($user);

        $view = app(GroupController::class)->index();
        $generalGroups = collect($view->getData()['generalGroups']);

        $this->assertTrue(
            $generalGroups->contains(fn (Group $group): bool =>
                (int) $group->governance_area_id === (int) $area->id
                && $group->dimension_key === 'public'
                && $group->dimension_value_key === 'all'
            ),
            'Stage C must materialize and expose the canonical public membership for the current Primary Residence.',
        );

        $this->assertFalse(
            $generalGroups->contains(fn (Group $group): bool => $group->is($legacyGroup)),
            'When canonical group cutover is enabled, legacy spatial memberships must not remain authoritative in My Groups.',
        );
    }
}
