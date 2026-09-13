<?php

namespace Tests\Feature\LocationGovernance;

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

        $response = $this->actingAs($user)->get('/groups');

        $response->assertOk();
        $response->assertViewHas('generalGroups', function ($groups) use ($area, $legacyGroup): bool {
            $groups = collect($groups);

            $hasCanonicalPublic = $groups->contains(fn (Group $group): bool =>
                (int) $group->governance_area_id === (int) $area->id
                && $group->dimension_key === 'public'
                && $group->dimension_value_key === 'all'
            );

            $hasLegacySpatial = $groups->contains(fn (Group $group): bool => $group->is($legacyGroup));

            return $hasCanonicalPublic && ! $hasLegacySpatial;
        });
    }
}
