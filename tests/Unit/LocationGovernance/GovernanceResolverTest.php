<?php

namespace Tests\Unit\LocationGovernance;

use App\Models\GovernanceArea;
use App\Services\LocationGovernance\GovernanceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class GovernanceResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_governance_topology_is_independent_from_location_tree(): void
    {
        $schema = LocationFixture::iranSchema();
        $locations = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood', 'street',
        ]);

        $iran = GovernanceArea::factory()->official()->create([
            'key' => 'ec-ir',
            'country_code' => 'IR',
            'governance_type' => 'country',
            'rank' => 100,
        ]);
        $sari = GovernanceArea::factory()->official()->create([
            'parent_id' => $iran->id,
            'key' => 'ec-sari',
            'country_code' => 'IR',
            'governance_type' => 'city',
            'rank' => 500,
        ]);
        $neighborhood = GovernanceArea::factory()->official()->create([
            'parent_id' => $sari->id,
            'key' => 'ec-balanaheleh',
            'country_code' => 'IR',
            'governance_type' => 'local',
            'rank' => 900,
        ]);

        $iran->locations()->attach($locations->firstWhere('level', 'country')->id);
        $sari->locations()->attach($locations->firstWhere('level', 'city')->id);
        $neighborhood->locations()->attach($locations->firstWhere('level', 'neighborhood')->id);

        $areas = app(GovernanceResolver::class)
            ->officialAreasForResidence($locations->last())
            ->pluck('id')
            ->all();

        $this->assertSame([$neighborhood->id, $sari->id, $iran->id], $areas);
        $this->assertNotSame($locations->firstWhere('level', 'neighborhood')->id, $neighborhood->id);
    }

    public function test_deepest_mapped_official_area_becomes_base_for_urban_residence(): void
    {
        $schema = LocationFixture::iranSchema();
        $locations = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood', 'street', 'alley',
        ]);

        $city = GovernanceArea::factory()->official()->create(['key' => 'urban-city', 'rank' => 500]);
        $local = GovernanceArea::factory()->official()->create([
            'parent_id' => $city->id,
            'key' => 'urban-local',
            'rank' => 900,
        ]);

        $city->locations()->attach($locations->firstWhere('level', 'city')->id);
        $local->locations()->attach($locations->firstWhere('level', 'neighborhood')->id);

        $resolved = app(GovernanceResolver::class)->baseOfficialAreaForResidence($locations->last());

        $this->assertTrue($resolved->is($local));
    }

    public function test_village_with_neighborhood_uses_neighborhood_as_base_without_hard_coding_urban_shape(): void
    {
        $schema = LocationFixture::iranSchema();
        $locations = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'rural_district', 'village', 'neighborhood',
        ]);

        $village = GovernanceArea::factory()->official()->create(['key' => 'rural-village', 'rank' => 800]);
        $local = GovernanceArea::factory()->official()->create([
            'parent_id' => $village->id,
            'key' => 'rural-local',
            'rank' => 900,
        ]);

        $village->locations()->attach($locations->firstWhere('level', 'village')->id);
        $local->locations()->attach($locations->firstWhere('level', 'neighborhood')->id);

        $resolved = app(GovernanceResolver::class)->baseOfficialAreaForResidence($locations->last());

        $this->assertTrue($resolved->is($local));
    }

    public function test_village_without_neighborhood_can_itself_be_the_base_official_scope(): void
    {
        $schema = LocationFixture::iranSchema();
        $locations = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'rural_district', 'village',
        ]);

        $village = GovernanceArea::factory()->official()->create(['key' => 'small-village', 'rank' => 800]);
        $village->locations()->attach($locations->last()->id);

        $resolved = app(GovernanceResolver::class)->baseOfficialAreaForResidence($locations->last());

        $this->assertTrue($resolved->is($village));
    }

    public function test_official_ancestors_follow_governance_parentage_not_location_parentage(): void
    {
        $root = GovernanceArea::factory()->official()->create(['key' => 'gov-root', 'rank' => 100]);
        $middle = GovernanceArea::factory()->official()->create([
            'parent_id' => $root->id,
            'key' => 'gov-middle',
            'rank' => 500,
        ]);
        $leaf = GovernanceArea::factory()->official()->create([
            'parent_id' => $middle->id,
            'key' => 'gov-leaf',
            'rank' => 900,
        ]);

        $ancestors = app(GovernanceResolver::class)
            ->officialAncestors($leaf)
            ->pluck('id')
            ->all();

        $this->assertSame([$middle->id, $root->id], $ancestors);
    }
}
