<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class GovernanceMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_governance_area_may_map_multiple_locations_without_merging_their_identity(): void
    {
        $schema = LocationFixture::iranSchema();
        $pathA = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city']);
        $pathB = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city']);

        $area = GovernanceArea::factory()->official()->create([
            'key' => 'multi-location-area',
            'country_code' => 'IR',
            'governance_type' => 'city_equivalent',
        ]);

        $cityA = $pathA->last();
        $cityB = $pathB->last();
        $area->locations()->attach([$cityA->id, $cityB->id]);

        $mappedIds = $area->fresh('locations')->locations->pluck('id')->sort()->values()->all();

        $this->assertSame(2, count($mappedIds));
        $this->assertSame(collect([$cityA->id, $cityB->id])->sort()->values()->all(), $mappedIds);
        $this->assertNotSame($cityA->id, $cityB->id);
    }

    public function test_same_location_may_be_mapped_to_distinct_official_and_community_areas(): void
    {
        $schema = LocationFixture::iranSchema();
        $location = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood',
        ])->last();

        $official = GovernanceArea::factory()->official()->create(['key' => 'official-local']);
        $community = GovernanceArea::factory()->community()->create(['key' => 'community-local']);

        $official->locations()->attach($location->id);
        $community->locations()->attach($location->id);

        $location->load('governanceAreas');

        $this->assertCount(2, $location->governanceAreas);
        $this->assertTrue($location->governanceAreas->contains(fn ($area) => $area->is($official)));
        $this->assertTrue($location->governanceAreas->contains(fn ($area) => $area->is($community)));
    }

    public function test_governance_area_identity_survives_location_rename(): void
    {
        $schema = LocationFixture::iranSchema();
        $location = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last();
        $area = GovernanceArea::factory()->official()->create(['key' => 'stable-governance-id']);
        $area->locations()->attach($location->id);

        $originalAreaId = $area->id;
        $location->update(['canonical_name' => 'Renamed City', 'name' => 'Renamed City']);

        $this->assertSame($originalAreaId, $area->fresh()->id);
        $this->assertTrue($area->fresh('locations')->locations->contains(fn ($mapped) => $mapped->id === $location->id));
    }
}
