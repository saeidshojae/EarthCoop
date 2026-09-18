<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\LocationStructureClaim;
use App\Models\User;
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

        $this->assertSame(['neighborhood'], $policy->effectiveChildTypeCodes($city, collect([$singleRegion])));
        $this->assertSame(['street'], $policy->effectiveChildTypeCodes($region, collect([$noNeighborhoodRegion])));
        $this->assertSame(['street'], $policy->effectiveChildTypeCodes($village, collect([$noNeighborhoodVillage])));
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
}
