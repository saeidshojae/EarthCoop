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
}
