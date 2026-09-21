<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\LocationStructureClaim;
use App\Models\User;
use App\Services\LocationGovernance\GovernanceResolver;
use App\Services\LocationGovernance\LocationStructureClaimPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class LocationStructureClaimPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_claim_types_are_allowed_only_on_their_structural_location_types(): void
    {
        $schema = LocationFixture::iranSchema();
        $policy = app(LocationStructureClaimPolicy::class);
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $region = LocationFixture::createPath($schema, ['country','province','county','section','city','urban_region'])->last();
        $village = LocationFixture::createPath($schema, ['country','province','county','section','rural_district','village'])->last();

        $this->assertSame(['single_urban_region', 'no_urban_region'], $policy->allowedClaimTypes($city));
        $this->assertSame(['single_neighborhood', 'no_neighborhood'], $policy->allowedClaimTypes($region));
        $this->assertSame(['single_neighborhood', 'no_neighborhood'], $policy->allowedClaimTypes($village));
        $this->assertNotContains('no_neighborhood', $policy->allowedClaimTypes($city));
    }

    public function test_claim_policy_is_driven_by_schema_metadata_and_combined_city_claim_is_contextual(): void
    {
        $schema = LocationFixture::iranSchema();
        $policy = app(LocationStructureClaimPolicy::class);
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $cityType = $schema->types->firstWhere('key', 'city');

        $pivot = \App\Models\LocationSchemaType::query()
            ->where('location_schema_id', $schema->id)
            ->where('location_type_id', $cityType->id)
            ->firstOrFail();
        $pivot->forceFill(['metadata' => array_merge($pivot->metadata ?? [], [
            'structural_claim_types' => ['no_urban_region'],
            'structural_claim_types_after' => [
                'no_urban_region' => ['no_neighborhood'],
            ],
        ])])->save();

        $this->assertSame(['no_urban_region'], $policy->allowedClaimTypes($city));
        $this->assertFalse($policy->allowsClaimType($city, 'no_neighborhood', collect()));

        $first = LocationStructureClaim::create([
            'location_id'=>$city->id,
            'claim_type'=>'no_urban_region',
            'status'=>'pending',
            'proposer_user_id'=>User::factory()->create()->id,
        ]);

        $this->assertTrue($policy->allowsClaimType($city, 'no_neighborhood', collect([$first])));
    }

    public function test_effective_children_skip_only_the_claimed_governance_tier_and_preserve_micro_locations(): void
    {
        $schema = LocationFixture::iranSchema();
        $policy = app(LocationStructureClaimPolicy::class);
        $user = User::factory()->create();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $region = LocationFixture::createPath($schema, ['country','province','county','section','city','urban_region'])->last();
        $village = LocationFixture::createPath($schema, ['country','province','county','section','rural_district','village'])->last();

        $singleRegion = LocationStructureClaim::create(['location_id'=>$city->id,'claim_type'=>'single_urban_region','status'=>'approved','proposer_user_id'=>$user->id,'approved_at'=>now()]);
        $noNeighborhoodRegion = LocationStructureClaim::create(['location_id'=>$region->id,'claim_type'=>'no_neighborhood','status'=>'approved','proposer_user_id'=>$user->id,'approved_at'=>now()]);
        $noNeighborhoodVillage = LocationStructureClaim::create(['location_id'=>$village->id,'claim_type'=>'no_neighborhood','status'=>'approved','proposer_user_id'=>$user->id,'approved_at'=>now()]);

        $this->assertSame(['urban_region'], $policy->effectiveChildTypeCodes($city, collect([$singleRegion])));
        $this->assertSame(['street'], $policy->effectiveChildTypeCodes($region, collect([$noNeighborhoodRegion])));
        $this->assertSame(['street'], $policy->effectiveChildTypeCodes($village, collect([$noNeighborhoodVillage])));
        $singleNeighborhoodRegion = LocationStructureClaim::create(['location_id'=>$region->id,'claim_type'=>'single_neighborhood','status'=>'approved','proposer_user_id'=>$user->id,'approved_at'=>now()]);
        $singleNeighborhoodVillage = LocationStructureClaim::create(['location_id'=>$village->id,'claim_type'=>'single_neighborhood','status'=>'approved','proposer_user_id'=>$user->id,'approved_at'=>now()]);

        $this->assertSame(['neighborhood'], $policy->effectiveChildTypeCodes($region, collect([$singleNeighborhoodRegion])));
        $this->assertSame(['neighborhood'], $policy->effectiveChildTypeCodes($village, collect([$singleNeighborhoodVillage])));
    }

    public function test_combined_city_without_region_or_neighborhood_can_continue_to_micro_locations(): void
    {
        $schema = LocationFixture::iranSchema();
        $policy = app(LocationStructureClaimPolicy::class);
        $user = User::factory()->create();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();

        $claims = collect([
            LocationStructureClaim::create(['location_id'=>$city->id,'claim_type'=>'no_urban_region','status'=>'approved','proposer_user_id'=>$user->id,'approved_at'=>now()]),
            LocationStructureClaim::create(['location_id'=>$city->id,'claim_type'=>'no_neighborhood','status'=>'approved','proposer_user_id'=>$user->id,'approved_at'=>now()]),
        ]);

        $this->assertSame(['street'], $policy->effectiveChildTypeCodes($city, $claims));
    }
    public function test_pending_structural_claim_does_not_create_or_replace_official_governance_topology(): void
    {
        $schema = LocationFixture::iranSchema();
        $user = User::factory()->create();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $street = \App\Models\Location::create([
            'location_schema_id' => $schema->id,
            'location_type_id' => $schema->types->firstWhere('key', 'street')->id,
            'parent_id' => $city->id,
            'country_code' => 'IR',
            'canonical_name' => 'Direct Structural Street',
            'status' => 'active',
        ]);
        $area = GovernanceArea::create([
            'key' => 'test-city-official',
            'country_code' => 'IR',
            'governance_type' => 'city',
            'area_kind' => 'official',
            'canonical_name' => 'Test City',
            'rank' => 500,
            'status' => 'active',
        ]);
        $area->locations()->attach($city->id);

        LocationStructureClaim::create([
            'location_id' => $city->id,
            'claim_type' => 'no_urban_region',
            'status' => 'pending',
            'proposer_user_id' => $user->id,
        ]);

        $resolver = app(GovernanceResolver::class);

        $this->assertSame($area->id, $resolver->baseOfficialAreaForResidence($street)?->id);
        $this->assertSame(1, GovernanceArea::query()->where('area_kind', 'official')->count());
        $this->assertSame([$area->id], $city->governanceAreas()->official()->pluck('governance_areas.id')->all());
    }

}
