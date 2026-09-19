<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\Location;
use App\Models\LocationSchema;
use App\Models\LocationStructureClaim;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class LocationSelectionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_governance_rollout_flags_default_to_false(): void
    {
        $this->assertFalse((bool) config('location-governance.runtime_enabled'));
        $this->assertFalse((bool) config('location-governance.registration_enabled'));
        $this->assertFalse((bool) config('location-governance.groups_enabled'));
        $this->assertFalse((bool) config('location-governance.elections_enabled'));
    }

    public function test_root_options_are_schema_driven_localized_and_hide_pending_locations(): void
    {
        config(['location-governance.runtime_enabled' => true]);
        app()->setLocale('fa');

        $schema = LocationFixture::iranSchema();
        $countryType = $schema->types->firstWhere('key', 'country');

        $active = Location::factory()->create([
            'location_schema_id' => $schema->id,
            'location_type_id' => $countryType->id,
            'country_code' => 'IR',
            'name' => 'Iran',
            'canonical_name' => 'Iran',
            'localized_names' => ['fa' => 'ایران'],
            'level' => 'country',
            'status' => 'active',
        ]);

        Location::factory()->create([
            'location_schema_id' => $schema->id,
            'location_type_id' => $countryType->id,
            'country_code' => 'IR',
            'name' => 'Pending Iran root',
            'canonical_name' => 'Pending Iran root',
            'localized_names' => ['fa' => 'ایران در انتظار بررسی'],
            'level' => 'country',
            'status' => 'pending',
        ]);

        $response = $this->getJson('/location/options/root?country=IR');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $active->id);
        $response->assertJsonPath('data.0.type_key', 'country');
        $response->assertJsonPath('data.0.label', 'ایران');
        $response->assertJsonPath('data.0.is_residence_endpoint', false);
        $response->assertJsonPath('data.0.has_children', false);
        $response->assertJsonPath('data.0.status', 'active');
    }

    public function test_root_without_country_filter_exposes_active_roots_from_multiple_country_schemas(): void
    {
        config(['location-governance.runtime_enabled' => true]);

        $iranSchema = LocationFixture::iranSchema();
        $countryType = $iranSchema->types->firstWhere('key', 'country');
        $iran = Location::factory()->create([
            'location_schema_id' => $iranSchema->id,
            'location_type_id' => $countryType->id,
            'country_code' => 'IR',
            'name' => 'Iran',
            'canonical_name' => 'Iran',
            'level' => 'country',
            'status' => 'active',
        ]);

        $alternateSchema = LocationSchema::factory()->create([
            'key' => 'us-reference-v1',
            'country_code' => 'US',
            'name' => 'US reference geography',
            'version' => '1',
            'status' => 'active',
        ]);
        $alternateSchema->types()->attach($countryType->id, [
            'is_root' => true,
            'is_residence_endpoint' => false,
            'metadata' => json_encode(['crowdsourced_proposal_allowed' => false]),
        ]);
        $unitedStates = Location::factory()->create([
            'location_schema_id' => $alternateSchema->id,
            'location_type_id' => $countryType->id,
            'country_code' => 'US',
            'name' => 'United States',
            'canonical_name' => 'United States',
            'level' => 'country',
            'status' => 'active',
        ]);

        $global = $this->getJson('/location/options/root');

        $global->assertOk();
        $this->assertEqualsCanonicalizing(
            [$iran->id, $unitedStates->id],
            collect($global->json('data'))->pluck('id')->all(),
        );

        $filtered = $this->getJson('/location/options/root?country=US');
        $filtered->assertOk()->assertJsonCount(1, 'data');
        $filtered->assertJsonPath('data.0.id', $unitedStates->id);
    }

    public function test_children_expose_structural_choices_and_effective_real_levels_without_fake_locations(): void
    {
        config(['location-governance.runtime_enabled' => true]);
        app()->setLocale('fa');

        $schema = LocationFixture::iranSchema();
        $user = User::factory()->create();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'], ['Iran','Mazandaran','Sari','Chahardangeh','Kiasar'])->last();
        $region = LocationFixture::createPath($schema, ['country','province','county','section','city','urban_region'], ['Iran','Mazandaran','Sari','Central','Sari','Region 6'])->last();
        $village = LocationFixture::createPath($schema, ['country','province','county','section','rural_district','village'], ['Iran','Mazandaran','Sari','Section','District','Village'])->last();

        $singleRegion = LocationStructureClaim::create(['location_id'=>$city->id,'claim_type'=>'single_urban_region','status'=>'pending','proposer_user_id'=>$user->id]);
        LocationStructureClaim::create(['location_id'=>$region->id,'claim_type'=>'no_neighborhood','status'=>'approved','proposer_user_id'=>$user->id,'approved_at'=>now()]);
        LocationStructureClaim::create(['location_id'=>$village->id,'claim_type'=>'no_neighborhood','status'=>'approved','proposer_user_id'=>$user->id,'approved_at'=>now()]);

        $cityResponse = $this->getJson('/location/options/'.$city->id.'/children')->assertOk();
        $this->assertContains('single_urban_region', collect($cityResponse->json('structural_choices'))->pluck('claim_type')->all());
        $this->assertContains('neighborhood', collect($cityResponse->json('effective_allowed_types'))->pluck('key')->all());
        $this->assertSame('pending', collect($cityResponse->json('structural_choices'))->firstWhere('claim_type', 'single_urban_region')['status']);

        foreach ([$region, $village] as $base) {
            $response = $this->getJson('/location/options/'.$base->id.'/children')->assertOk();
            $this->assertContains('street', collect($response->json('effective_allowed_types'))->pluck('key')->all());
            $this->assertTrue((bool) $response->json('official_governance_base'));
        }
    }

    public function test_combined_city_claims_expose_micro_children_as_effective_residence_continuation(): void
    {
        config(['location-governance.runtime_enabled' => true]);

        $schema = LocationFixture::iranSchema();
        $user = User::factory()->create();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();

        foreach (['no_urban_region','no_neighborhood'] as $type) {
            LocationStructureClaim::create(['location_id'=>$city->id,'claim_type'=>$type,'status'=>'approved','proposer_user_id'=>$user->id,'approved_at'=>now()]);
        }

        $response = $this->getJson('/location/options/'.$city->id.'/children')->assertOk();
        $this->assertContains('street', collect($response->json('effective_allowed_types'))->pluck('key')->all());
        $this->assertTrue((bool) $response->json('official_governance_base'));
    }

    public function test_pending_city_collapse_claims_allow_residence_detail_but_do_not_grant_official_base_authority(): void
    {
        config(['location-governance.runtime_enabled' => true]);

        $schema = LocationFixture::iranSchema();
        $user = User::factory()->create();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();

        foreach (['no_urban_region', 'no_neighborhood'] as $type) {
            LocationStructureClaim::create([
                'location_id' => $city->id,
                'claim_type' => $type,
                'status' => 'pending',
                'proposer_user_id' => $user->id,
            ]);
        }

        $response = $this->getJson('/location/options/'.$city->id.'/children')->assertOk();

        $this->assertContains('street', collect($response->json('effective_allowed_types'))->pluck('key')->all());
        $this->assertFalse((bool) $response->json('official_governance_base'));
    }

    public function test_pending_no_neighborhood_claim_on_region_and_village_opens_micro_residence_without_claiming_official_base(): void
    {
        config(['location-governance.runtime_enabled' => true]);

        $schema = LocationFixture::iranSchema();
        $user = User::factory()->create();
        $region = LocationFixture::createPath($schema, ['country','province','county','section','city','urban_region'])->last();
        $village = LocationFixture::createPath($schema, ['country','province','county','section','rural_district','village'])->last();

        foreach ([$region, $village] as $base) {
            LocationStructureClaim::create([
                'location_id' => $base->id,
                'claim_type' => 'no_neighborhood',
                'status' => 'pending',
                'proposer_user_id' => $user->id,
            ]);

            $response = $this->getJson('/location/options/'.$base->id.'/children')->assertOk();

            $this->assertContains('street', collect($response->json('effective_allowed_types'))->pluck('key')->all());
            $this->assertFalse((bool) $response->json('official_governance_base'));
        }
    }

    public function test_children_follow_schema_branching_and_report_endpoint_and_child_state(): void
    {
        config(['location-governance.runtime_enabled' => true]);

        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section'],
            ['Iran', 'Mazandaran', 'Sari County', 'Central Section'],
        );
        $section = $path->last();

        $cityType = $schema->types->firstWhere('key', 'city');
        $ruralType = $schema->types->firstWhere('key', 'rural_district');
        $urbanRegionType = $schema->types->firstWhere('key', 'urban_region');

        $city = Location::factory()->create([
            'parent_id' => $section->id,
            'location_schema_id' => $schema->id,
            'location_type_id' => $cityType->id,
            'country_code' => 'IR',
            'name' => 'Sari',
            'canonical_name' => 'Sari',
            'level' => 'city',
            'status' => 'active',
        ]);
        Location::factory()->create([
            'parent_id' => $city->id,
            'location_schema_id' => $schema->id,
            'location_type_id' => $urbanRegionType->id,
            'country_code' => 'IR',
            'name' => 'Region 1',
            'canonical_name' => 'Region 1',
            'level' => 'urban_region',
            'status' => 'active',
        ]);

        $rural = Location::factory()->create([
            'parent_id' => $section->id,
            'location_schema_id' => $schema->id,
            'location_type_id' => $ruralType->id,
            'country_code' => 'IR',
            'name' => 'Chahardangeh',
            'canonical_name' => 'Chahardangeh',
            'level' => 'rural_district',
            'status' => 'active',
        ]);

        Location::factory()->create([
            'parent_id' => $section->id,
            'location_schema_id' => $schema->id,
            'location_type_id' => $cityType->id,
            'country_code' => 'IR',
            'name' => 'Pending City',
            'canonical_name' => 'Pending City',
            'level' => 'city',
            'status' => 'pending',
        ]);

        $response = $this->getJson('/location/options/'.$section->id.'/children');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $rows = collect($response->json('data'))->keyBy('id');
        $this->assertSame('city', $rows[$city->id]['type_key']);
        $this->assertTrue($rows[$city->id]['is_residence_endpoint']);
        $this->assertTrue($rows[$city->id]['has_children']);
        $this->assertSame('active', $rows[$city->id]['status']);

        $this->assertSame('rural_district', $rows[$rural->id]['type_key']);
        $this->assertFalse($rows[$rural->id]['is_residence_endpoint']);
        $this->assertFalse($rows[$rural->id]['has_children']);
    }
}
