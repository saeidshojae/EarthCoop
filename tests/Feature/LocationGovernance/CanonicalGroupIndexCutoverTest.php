<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\Group;
use App\Models\Location;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\MembershipFixture;
use Tests\TestCase;

class CanonicalGroupIndexCutoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_groups_index_resolves_canonical_memberships_when_groups_cutover_is_enabled(): void
    {
        $this->enableStageC();

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
                && $group->dimension_value_key === 'public'
            );

            $hasLegacySpatial = $groups->contains(fn (Group $group): bool => $group->is($legacyGroup));

            return $hasCanonicalPublic && ! $hasLegacySpatial;
        });
    }

    public function test_residence_transfer_atomically_deactivates_stale_canonical_memberships_and_activates_current_scope(): void
    {
        $this->enableStageC();

        ['user' => $user, 'area' => $oldArea, 'endpoint' => $oldEndpoint] = MembershipFixture::canonicalUser();

        $this->actingAs($user)->get('/groups')->assertOk();

        $oldPublicGroup = Group::query()
            ->where('governance_area_id', $oldArea->id)
            ->where('dimension_key', 'public')
            ->where('dimension_value_key', 'public')
            ->firstOrFail();

        $this->assertSame(1, (int) $user->groups()->whereKey($oldPublicGroup->id)->firstOrFail()->pivot->status);

        $newEndpoint = Location::factory()->create([
            'parent_id' => $oldEndpoint->parent_id,
            'location_schema_id' => $oldEndpoint->location_schema_id,
            'location_type_id' => $oldEndpoint->location_type_id,
            'country_code' => 'IR',
            'name' => 'ساری جدید',
            'canonical_name' => 'New Sari',
            'level' => $oldEndpoint->level,
            'status' => 'active',
        ]);

        $newArea = GovernanceArea::create([
            'key' => 'ir.new-sari',
            'country_code' => 'IR',
            'governance_type' => 'city',
            'area_kind' => 'official',
            'canonical_name' => 'New Sari',
            'rank' => 10,
            'status' => 'active',
        ]);
        $newArea->locations()->attach($newEndpoint->id);

        app(ResidenceService::class)->transferPrimaryResidence(
            $user,
            $newEndpoint,
            $user,
            'stage_c_reconciliation_test',
        );

        $newPublicGroup = Group::query()
            ->where('governance_area_id', $newArea->id)
            ->where('dimension_key', 'public')
            ->where('dimension_value_key', 'public')
            ->firstOrFail();

        $this->assertSame(0, (int) $user->groups()->whereKey($oldPublicGroup->id)->firstOrFail()->pivot->status);
        $this->assertSame(1, (int) $user->groups()->whereKey($newPublicGroup->id)->firstOrFail()->pivot->status);
    }

    public function test_base_scope_is_active_and_all_upstream_canonical_memberships_are_observer_roles(): void
    {
        $this->enableStageC();

        ['user' => $user, 'area' => $baseArea] = MembershipFixture::canonicalUser();

        $country = GovernanceArea::create([
            'key' => 'ir.country',
            'country_code' => 'IR',
            'governance_type' => 'country',
            'area_kind' => 'official',
            'canonical_name' => 'Iran',
            'rank' => 1,
            'status' => 'active',
        ]);
        $baseArea->update(['parent_id' => $country->id, 'rank' => 10]);

        $this->actingAs($user)->get('/groups')->assertOk();

        foreach (['public', 'profession', 'specialty', 'age', 'gender'] as $dimensionKey) {
            $baseGroup = Group::query()
                ->where('governance_area_id', $baseArea->id)
                ->where('dimension_key', $dimensionKey)
                ->firstOrFail();
            $upstreamGroup = Group::query()
                ->where('governance_area_id', $country->id)
                ->where('dimension_key', $dimensionKey)
                ->firstOrFail();

            $basePivot = $user->groups()->whereKey($baseGroup->id)->firstOrFail()->pivot;
            $upstreamPivot = $user->groups()->whereKey($upstreamGroup->id)->firstOrFail()->pivot;

            $this->assertSame(1, (int) $basePivot->role, "Base {$dimensionKey} group must be active.");
            $this->assertSame(0, (int) $upstreamPivot->role, "Upstream {$dimensionKey} group must be observer.");
        }
    }

    public function test_active_privileged_upstream_role_is_not_overwritten_by_membership_reconciliation(): void
    {
        $this->enableStageC();

        ['user' => $user, 'area' => $baseArea] = MembershipFixture::canonicalUser();

        $country = GovernanceArea::create([
            'key' => 'ir.country.privileged',
            'country_code' => 'IR',
            'governance_type' => 'country',
            'area_kind' => 'official',
            'canonical_name' => 'Iran',
            'rank' => 1,
            'status' => 'active',
        ]);
        $baseArea->update(['parent_id' => $country->id, 'rank' => 10]);

        $this->actingAs($user)->get('/groups')->assertOk();

        $countryPublic = Group::query()
            ->where('governance_area_id', $country->id)
            ->where('dimension_key', 'public')
            ->where('dimension_value_key', 'public')
            ->firstOrFail();

        $user->groups()->updateExistingPivot($countryPublic->id, [
            'status' => 1,
            'role' => 3,
        ]);

        $this->actingAs($user)->get('/groups')->assertOk();

        $this->assertSame(
            3,
            (int) $user->groups()->whereKey($countryPublic->id)->firstOrFail()->pivot->role,
        );
    }

    private function enableStageC(): void
    {
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
            'location-governance.groups_enabled' => true,
        ]);
    }
}
