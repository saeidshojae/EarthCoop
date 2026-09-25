<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\Group;
use App\Models\User;
use App\Services\Elections\ElectionGroupHierarchyResolver;
use App\Services\Elections\ElectionPolicyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionCanonicalCutoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_hierarchy_follows_official_governance_parent_and_same_dimension_track(): void
    {
        config()->set('location-governance.elections_enabled', true);

        $parentArea = GovernanceArea::factory()->official()->create([
            'governance_type' => 'province',
            'status' => 'active',
            'parent_id' => null,
            'rank' => 100,
        ]);
        $childArea = GovernanceArea::factory()->official()->create([
            'governance_type' => 'city',
            'status' => 'active',
            'parent_id' => $parentArea->id,
            'rank' => 50,
        ]);

        $parent = Group::create([
            'name' => 'Canonical province public',
            'group_type' => 0,
            'governance_area_id' => $parentArea->id,
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
        ]);
        $child = Group::create([
            'name' => 'Canonical city public',
            'group_type' => 0,
            'governance_area_id' => $childArea->id,
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
        ]);

        $resolver = app(ElectionGroupHierarchyResolver::class);
        $user = User::factory()->create();

        $this->assertSame($parent->id, $resolver->higherGroup($child, $user)?->id);
        $this->assertTrue($resolver->sameTrack($child, $parent));
        $this->assertSame(1, $resolver->effectiveStructuralChildCount($parent));
        $this->assertTrue($resolver->isSoleStructuralConstituency($child, $parent));
    }

    public function test_canonical_formal_hierarchy_excludes_community_and_inactive_children(): void
    {
        config()->set('location-governance.elections_enabled', true);

        $parentArea = GovernanceArea::factory()->official()->create(['status' => 'active']);
        $officialChild = GovernanceArea::factory()->official()->create([
            'status' => 'active',
            'parent_id' => $parentArea->id,
        ]);
        GovernanceArea::factory()->community()->create([
            'status' => 'active',
            'parent_id' => $parentArea->id,
        ]);
        GovernanceArea::factory()->official()->create([
            'status' => 'inactive',
            'parent_id' => $parentArea->id,
        ]);

        $parent = Group::create([
            'name' => 'Parent',
            'group_type' => 0,
            'governance_area_id' => $parentArea->id,
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
        ]);
        $child = Group::create([
            'name' => 'Official child',
            'group_type' => 0,
            'governance_area_id' => $officialChild->id,
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
        ]);

        $resolver = app(ElectionGroupHierarchyResolver::class);

        $this->assertSame(1, $resolver->effectiveStructuralChildCount($parent));
        $this->assertTrue($resolver->isSoleStructuralConstituency($child, $parent));
    }

    public function test_collapsed_city_topology_skips_synthetic_region_and_uses_real_neighborhoods(): void
    {
        config()->set('location-governance.elections_enabled', true);

        $cityArea = GovernanceArea::factory()->official()->create([
            'governance_type' => 'city',
            'status' => 'active',
            'parent_id' => null,
            'rank' => 500,
        ]);
        $firstNeighborhoodArea = GovernanceArea::factory()->official()->create([
            'governance_type' => 'neighborhood',
            'status' => 'active',
            'parent_id' => $cityArea->id,
            'rank' => 900,
        ]);
        $secondNeighborhoodArea = GovernanceArea::factory()->official()->create([
            'governance_type' => 'neighborhood',
            'status' => 'active',
            'parent_id' => $cityArea->id,
            'rank' => 900,
        ]);

        $city = $this->canonicalPublicGroup($cityArea, 'Collapsed city');
        $firstNeighborhood = $this->canonicalPublicGroup($firstNeighborhoodArea, 'Real neighborhood A');
        $this->canonicalPublicGroup($secondNeighborhoodArea, 'Real neighborhood B');

        $resolver = app(ElectionGroupHierarchyResolver::class);
        $user = User::factory()->create();

        $this->assertSame($city->id, $resolver->higherGroup($firstNeighborhood, $user)?->id);
        $this->assertSame(2, $resolver->effectiveStructuralChildCount($city));
        $this->assertFalse($resolver->isSoleStructuralConstituency($firstNeighborhood, $city));
        $this->assertSame([], $resolver->compressionChain($firstNeighborhood, $user));
    }

    public function test_collapsed_neighborhood_topology_makes_region_or_village_the_official_local_layer(): void
    {
        config()->set('location-governance.elections_enabled', true);

        foreach (['urban_region', 'village'] as $type) {
            $parentArea = GovernanceArea::factory()->official()->create([
                'governance_type' => $type,
                'status' => 'active',
                'parent_id' => null,
                'rank' => 700,
            ]);
            $parent = $this->canonicalPublicGroup($parentArea, 'Collapsed '.$type);

            $resolver = app(ElectionGroupHierarchyResolver::class);

            $this->assertSame(0, $resolver->effectiveStructuralChildCount($parent));
            $this->assertTrue($resolver->isIndependentElectoralLayer($parent));
        }
    }

    public function test_single_real_region_is_preserved_but_city_region_boundary_is_electorally_compressed(): void
    {
        config()->set('location-governance.elections_enabled', true);

        $cityArea = GovernanceArea::factory()->official()->create([
            'governance_type' => 'city',
            'status' => 'active',
            'parent_id' => null,
            'rank' => 500,
        ]);
        $regionArea = GovernanceArea::factory()->official()->create([
            'governance_type' => 'urban_region',
            'status' => 'active',
            'parent_id' => $cityArea->id,
            'rank' => 700,
        ]);
        $firstNeighborhoodArea = GovernanceArea::factory()->official()->create([
            'governance_type' => 'local',
            'status' => 'active',
            'parent_id' => $regionArea->id,
            'rank' => 900,
        ]);
        GovernanceArea::factory()->official()->create([
            'governance_type' => 'local',
            'status' => 'active',
            'parent_id' => $regionArea->id,
            'rank' => 900,
        ]);

        $city = $this->canonicalPublicGroup($cityArea, 'Single-region city');
        $region = $this->canonicalPublicGroup($regionArea, 'Only real region');
        $neighborhood = $this->canonicalPublicGroup($firstNeighborhoodArea, 'Neighborhood A');

        $resolver = app(ElectionGroupHierarchyResolver::class);
        $user = User::factory()->create();

        $this->assertSame(1, $resolver->effectiveStructuralChildCount($city));
        $this->assertFalse($resolver->isIndependentElectoralLayer($city));
        $this->assertSame(2, $resolver->effectiveStructuralChildCount($region));
        $this->assertTrue($resolver->isIndependentElectoralLayer($region));
        $this->assertSame($region->id, $resolver->higherGroup($neighborhood, $user)?->id);
        $this->assertSame([$city->id], array_map(
            fn (Group $group): int => (int) $group->id,
            $resolver->compressionChain($region, $user),
        ));
    }

    public function test_single_real_neighborhood_compresses_region_or_village_boundary_without_deleting_location_layer(): void
    {
        config()->set('location-governance.elections_enabled', true);

        foreach (['urban_region', 'village'] as $parentType) {
            $parentArea = GovernanceArea::factory()->official()->create([
                'governance_type' => $parentType,
                'status' => 'active',
                'parent_id' => null,
                'rank' => 700,
            ]);
            $neighborhoodArea = GovernanceArea::factory()->official()->create([
                'governance_type' => 'local',
                'status' => 'active',
                'parent_id' => $parentArea->id,
                'rank' => 900,
            ]);

            $parent = $this->canonicalPublicGroup($parentArea, 'Single-neighborhood '.$parentType);
            $neighborhood = $this->canonicalPublicGroup($neighborhoodArea, 'Only real neighborhood '.$parentType);

            $resolver = app(ElectionGroupHierarchyResolver::class);
            $user = User::factory()->create();

            $this->assertSame(1, $resolver->effectiveStructuralChildCount($parent));
            $this->assertFalse($resolver->isIndependentElectoralLayer($parent));
            $this->assertTrue($resolver->isIndependentElectoralLayer($neighborhood));
            $this->assertSame([$parent->id], array_map(
                fn (Group $group): int => (int) $group->id,
                $resolver->compressionChain($neighborhood, $user),
            ));
        }
    }

    public function test_multiple_single_child_boundaries_compress_as_one_electoral_chain(): void
    {
        config()->set('location-governance.elections_enabled', true);

        $provinceArea = GovernanceArea::factory()->official()->create([
            'governance_type' => 'province',
            'status' => 'active',
            'parent_id' => null,
            'rank' => 200,
        ]);
        $cityArea = GovernanceArea::factory()->official()->create([
            'governance_type' => 'city',
            'status' => 'active',
            'parent_id' => $provinceArea->id,
            'rank' => 500,
        ]);
        $regionArea = GovernanceArea::factory()->official()->create([
            'governance_type' => 'urban_region',
            'status' => 'active',
            'parent_id' => $cityArea->id,
            'rank' => 700,
        ]);
        $neighborhoodArea = GovernanceArea::factory()->official()->create([
            'governance_type' => 'local',
            'status' => 'active',
            'parent_id' => $regionArea->id,
            'rank' => 900,
        ]);

        $province = $this->canonicalPublicGroup($provinceArea, 'Province');
        $city = $this->canonicalPublicGroup($cityArea, 'City');
        $region = $this->canonicalPublicGroup($regionArea, 'Region');
        $neighborhood = $this->canonicalPublicGroup($neighborhoodArea, 'Neighborhood');

        $resolver = app(ElectionGroupHierarchyResolver::class);
        $user = User::factory()->create();

        $this->assertSame(
            [$region->id, $city->id, $province->id],
            array_map(
                fn (Group $group): int => (int) $group->id,
                $resolver->compressionChain($neighborhood, $user),
            ),
        );
        $this->assertNull($resolver->nextElectoralParent($neighborhood, $user));
    }

    public function test_canonical_policy_key_uses_governance_type_and_canonical_dimension(): void
    {
        config()->set('location-governance.elections_enabled', true);

        $area = GovernanceArea::factory()->official()->create([
            'governance_type' => 'city',
            'status' => 'active',
        ]);
        $resolver = app(ElectionPolicyResolver::class);

        $cases = [
            ['public', 'public', 'city'],
            ['profession', 'occupational_field:12', 'city_job'],
            ['specialty', 'experience_field:34', 'city_experience'],
            ['age', 'age_group:56', 'city_age'],
            ['gender', 'gender:female', 'city_gender'],
        ];

        foreach ($cases as [$dimension, $value, $expected]) {
            $group = new Group([
                'governance_area_id' => $area->id,
                'dimension_key' => $dimension,
                'dimension_value_key' => $value,
            ]);
            $group->setRelation('governanceArea', $area);

            $this->assertSame($expected, $resolver->levelKeyForGroup($group));
        }
    }

    public function test_legacy_policy_contract_remains_when_election_cutover_flag_is_off(): void
    {
        config()->set('location-governance.elections_enabled', false);

        $resolver = app(ElectionPolicyResolver::class);

        $this->assertSame('district_job', $resolver->levelKeyForGroup(new Group([
            'location_level' => 'section',
            'specialty_id' => 11,
        ])));
        $this->assertSame('city_experience', $resolver->levelKeyForGroup(new Group([
            'location_level' => 'city',
            'experience_id' => 22,
        ])));
    }
    private function canonicalPublicGroup(GovernanceArea $area, string $name): Group
    {
        return Group::create([
            'name' => $name,
            'group_type' => 0,
            'governance_area_id' => $area->id,
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
        ]);
    }
}
