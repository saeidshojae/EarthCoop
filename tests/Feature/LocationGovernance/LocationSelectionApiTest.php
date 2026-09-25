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

    public function test_ir_country_filter_prefers_active_v2_root_when_v1_and_v2_coexist(): void
    {
        config(['location-governance.runtime_enabled' => true]);

        $v1 = LocationFixture::iranSchema();
        $countryType = $v1->types->firstWhere('key', 'country');
        $v1Root = Location::factory()->create([
            'location_schema_id' => $v1->id,
            'location_type_id' => $countryType->id,
            'country_code' => 'IR',
            'name' => 'Iran v1',
            'canonical_name' => 'Iran v1',
            'level' => 'country',
            'status' => 'active',
        ]);

        $v2 = LocationSchema::query()->create([
            'key' => 'ir-reference-v2',
            'country_code' => 'IR',
            'name' => 'Iran 1404',
            'version' => 'v2',
            'status' => 'active',
        ]);
        $v2->types()->attach($countryType->id, [
            'is_root' => true,
            'is_residence_endpoint' => false,
            'sort_order' => 10,
            'metadata' => json_encode(['crowdsourced_proposal_allowed' => false]),
        ]);
        $v2Root = Location::factory()->create([
            'location_schema_id' => $v2->id,
            'location_type_id' => $countryType->id,
            'country_code' => 'IR',
            'name' => 'ایران',
            'canonical_name' => 'ایران',
            'localized_names' => ['fa' => 'ایران'],
            'level' => 'country',
            'status' => 'active',
        ]);

        $response = $this->getJson('/location/options/root?country=IR')->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $v2Root->id);
        $this->assertNotSame($v1Root->id, (int) $response->json('data.0.id'));
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
        $this->assertContains('urban_region', collect($cityResponse->json('effective_allowed_types'))->pluck('key')->all());
        $singleRegionChoice = collect($cityResponse->json('structural_choices'))->firstWhere('claim_type', 'single_urban_region');
        $this->assertSame('pending', $singleRegionChoice['status']);
        $this->assertSame($singleRegion->id, $singleRegionChoice['claim_id']);

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

    public function test_pending_city_collapse_claims_are_visible_but_only_affect_an_explicitly_selected_residence_path(): void
    {
        config(['location-governance.runtime_enabled' => true]);

        $schema = LocationFixture::iranSchema();
        $user = User::factory()->create();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();

        $claims = collect(['no_urban_region', 'no_neighborhood'])->map(fn (string $type) => LocationStructureClaim::create([
            'location_id' => $city->id,
            'claim_type' => $type,
            'status' => 'pending',
            'proposer_user_id' => $user->id,
        ]));

        $default = $this->getJson('/location/options/'.$city->id.'/children')->assertOk();
        $this->assertContains('urban_region', collect($default->json('effective_allowed_types'))->pluck('key')->all());
        $this->assertFalse((bool) $default->json('official_governance_base'));
        $this->assertSame('pending', collect($default->json('structural_choices'))->firstWhere('claim_type', 'no_urban_region')['status']);
        $this->assertFalse((bool) collect($default->json('structural_choices'))->firstWhere('claim_type', 'no_urban_region')['selected']);

        $query = http_build_query(['location_structure_claim_ids' => $claims->pluck('id')->all()]);
        $selected = $this->getJson('/location/options/'.$city->id.'/children?'.$query)->assertOk();
        $this->assertSame(['street'], collect($selected->json('effective_allowed_types'))->pluck('key')->all());
        $this->assertTrue((bool) collect($selected->json('structural_choices'))->firstWhere('claim_type', 'no_urban_region')['selected']);
        $this->assertFalse((bool) $selected->json('official_governance_base'));
    }

    public function test_pending_no_region_claim_does_not_block_another_user_from_proposing_a_real_region(): void
    {
        config(['location-governance.runtime_enabled' => true]);

        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();
        $regionType = $schema->types->firstWhere('key', 'urban_region');
        $claimOwner = User::factory()->create();
        $otherUser = User::factory()->create();

        $claim = LocationStructureClaim::create([
            'location_id' => $city->id,
            'claim_type' => 'no_urban_region',
            'status' => 'pending',
            'proposer_user_id' => $claimOwner->id,
        ]);

        $default = $this->actingAs($otherUser)->getJson('/location/options/'.$city->id.'/children')->assertOk();
        $default->assertJsonPath('effective_allowed_types.0.key', 'urban_region');
        $default->assertJsonPath('effective_allowed_types.0.proposal_allowed', true);

        $proposal = $this->actingAs($otherUser)->postJson('/locations/proposals', [
            'parent_location_id' => $city->id,
            'location_type_id' => $regionType->id,
            'canonical_name' => 'منطقه پیشنهادی برخلاف ادعای بدون منطقه',
        ])->assertCreated()->assertJsonPath('kind', 'proposal');

        $proposalId = (int) $proposal->json('id');
        $this->assertDatabaseHas('location_proposals', [
            'id' => $proposalId,
            'parent_location_id' => $city->id,
            'location_type_id' => $regionType->id,
            'proposer_user_id' => $otherUser->id,
        ]);

        $afterProposal = $this->actingAs($claimOwner)->getJson('/location/options/'.$city->id.'/children')->assertOk();
        $this->assertContains($proposalId, collect($afterProposal->json('proposals'))->pluck('id')->all());
        $this->assertSame('pending', $claim->fresh()->status);
    }

    public function test_pending_no_neighborhood_claim_on_region_and_village_requires_explicit_selection_to_open_micro_residence(): void
    {
        config(['location-governance.runtime_enabled' => true]);

        $schema = LocationFixture::iranSchema();
        $user = User::factory()->create();
        $region = LocationFixture::createPath($schema, ['country','province','county','section','city','urban_region'])->last();
        $village = LocationFixture::createPath($schema, ['country','province','county','section','rural_district','village'])->last();

        foreach ([$region, $village] as $base) {
            $claim = LocationStructureClaim::create([
                'location_id' => $base->id,
                'claim_type' => 'no_neighborhood',
                'status' => 'pending',
                'proposer_user_id' => $user->id,
            ]);

            $default = $this->getJson('/location/options/'.$base->id.'/children')->assertOk();
            $this->assertContains('neighborhood', collect($default->json('effective_allowed_types'))->pluck('key')->all());
            $this->assertFalse((bool) $default->json('official_governance_base'));
            $this->assertFalse((bool) $default->json('registration_endpoint_allowed'));

            $selected = $this->getJson('/location/options/'.$base->id.'/children?'.http_build_query([
                'location_structure_claim_ids' => [$claim->id],
            ]))->assertOk();
            $this->assertSame(['street'], collect($selected->json('effective_allowed_types'))->pluck('key')->all());
            $this->assertFalse((bool) $selected->json('official_governance_base'));
            $this->assertTrue((bool) $selected->json('registration_endpoint_allowed'));
        }
    }

    public function test_approving_structural_claim_changes_authority_signal_without_creating_synthetic_location(): void
    {
        config(['location-governance.runtime_enabled' => true]);

        $schema = LocationFixture::iranSchema();
        $user = User::factory()->create();
        $reviewer = User::factory()->create();
        $village = LocationFixture::createPath($schema, ['country','province','county','section','rural_district','village'])->last();

        $claim = LocationStructureClaim::create([
            'location_id' => $village->id,
            'claim_type' => 'no_neighborhood',
            'status' => 'pending',
            'proposer_user_id' => $user->id,
        ]);

        $beforeLocationIds = Location::query()->pluck('id')->sort()->values()->all();

        $pending = $this->getJson('/location/options/'.$village->id.'/children')->assertOk();
        $this->assertContains('neighborhood', collect($pending->json('effective_allowed_types'))->pluck('key')->all());
        $this->assertFalse((bool) $pending->json('official_governance_base'));

        $selectedPending = $this->getJson('/location/options/'.$village->id.'/children?'.http_build_query([
            'location_structure_claim_ids' => [$claim->id],
        ]))->assertOk();
        $this->assertSame(['street'], collect($selectedPending->json('effective_allowed_types'))->pluck('key')->all());

        app(\App\Services\LocationGovernance\LocationStructureClaimService::class)
            ->approve($claim, $reviewer, 'ساختار محل بررسی و تایید شد');

        $approved = $this->getJson('/location/options/'.$village->id.'/children')->assertOk();
        $this->assertContains('street', collect($approved->json('effective_allowed_types'))->pluck('key')->all());
        $this->assertTrue((bool) $approved->json('official_governance_base'));
        $this->assertTrue((bool) $approved->json('registration_endpoint_allowed'));
        $this->assertSame($beforeLocationIds, Location::query()->pluck('id')->sort()->values()->all());
        $this->assertFalse(Location::query()->where('parent_id', $village->id)->where('level', 'neighborhood')->exists());
    }

    public function test_approving_collapsed_city_claims_opens_micro_residence_without_creating_region_or_neighborhood(): void
    {
        config(['location-governance.runtime_enabled' => true]);

        $schema = LocationFixture::iranSchema();
        $user = User::factory()->create();
        $reviewer = User::factory()->create();
        $city = LocationFixture::createPath($schema, ['country','province','county','section','city'])->last();

        $regionClaim = LocationStructureClaim::create([
            'location_id' => $city->id,
            'claim_type' => 'no_urban_region',
            'status' => 'pending',
            'proposer_user_id' => $user->id,
        ]);
        $neighborhoodClaim = LocationStructureClaim::create([
            'location_id' => $city->id,
            'claim_type' => 'no_neighborhood',
            'status' => 'pending',
            'proposer_user_id' => $user->id,
        ]);

        $beforeLocationIds = Location::query()->pluck('id')->sort()->values()->all();
        $service = app(\App\Services\LocationGovernance\LocationStructureClaimService::class);
        $service->approve($regionClaim, $reviewer, 'شهر منطقه ندارد');
        $service->approve($neighborhoodClaim, $reviewer, 'شهر محله ندارد');

        $response = $this->getJson('/location/options/'.$city->id.'/children')->assertOk();

        $this->assertContains('street', collect($response->json('effective_allowed_types'))->pluck('key')->all());
        $this->assertTrue((bool) $response->json('official_governance_base'));
        $this->assertSame($beforeLocationIds, Location::query()->pluck('id')->sort()->values()->all());
        $this->assertFalse(Location::query()->where('parent_id', $city->id)->whereIn('level', ['urban_region','neighborhood'])->exists());
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
    public function test_single_structural_claims_preserve_real_location_tiers_and_do_not_mark_parent_as_governance_base(): void
    {
        config(['location-governance.runtime_enabled' => true]);

        $schema = LocationFixture::iranSchema();
        $user = User::factory()->create();
        $cityPath = LocationFixture::createPath($schema, ['country','province','county','section','city','urban_region','neighborhood']);
        $city = $cityPath->first(fn ($location) => $location->type?->key === 'city');
        $region = $cityPath->first(fn ($location) => $location->type?->key === 'urban_region');

        LocationStructureClaim::create([
            'location_id' => $city->id,
            'claim_type' => 'single_urban_region',
            'status' => 'approved',
            'proposer_user_id' => $user->id,
            'approved_at' => now(),
        ]);
        LocationStructureClaim::create([
            'location_id' => $region->id,
            'claim_type' => 'single_neighborhood',
            'status' => 'approved',
            'proposer_user_id' => $user->id,
            'approved_at' => now(),
        ]);

        $cityResponse = $this->getJson('/location/options/'.$city->id.'/children')->assertOk();
        $this->assertContains('urban_region', collect($cityResponse->json('effective_allowed_types'))->pluck('key')->all());
        $this->assertFalse((bool) $cityResponse->json('official_governance_base'));
        $this->assertNotContains('single_neighborhood', collect($cityResponse->json('structural_choices'))->pluck('claim_type')->all());

        $regionResponse = $this->getJson('/location/options/'.$region->id.'/children')->assertOk();
        $this->assertContains('neighborhood', collect($regionResponse->json('effective_allowed_types'))->pluck('key')->all());
        $this->assertFalse((bool) $regionResponse->json('official_governance_base'));
    }


}
