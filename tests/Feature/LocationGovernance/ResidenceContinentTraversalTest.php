<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\User;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class ResidenceContinentTraversalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
        ]);
        app()->setLocale('fa');
    }

    public function test_residence_root_starts_at_continent_and_bridges_country_to_canonical_location(): void
    {
        $schema = LocationFixture::iranSchema();
        $country = LocationFixture::createPath($schema, ['country'], ['ایران'])->first();
        [$global, $asia, $iran] = $this->governancePathFor($country);

        $this->getJson('/location/residence/options/root')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.identity', 'governance:'.$asia->id)
            ->assertJsonPath('data.0.type_key', 'continent')
            ->assertJsonPath('data.0.label', 'آسیا');

        $this->getJson('/location/residence/options/governance/'.$asia->id.'/children')->assertOk()
            ->assertJsonPath('data.0.identity', 'location:'.$country->id)
            ->assertJsonPath('data.0.type_key', 'country')
            ->assertJsonPath('data.0.label', 'ایران');
    }

    public function test_residence_country_menu_prefers_v2_iran_when_v1_and_v2_coexist(): void
    {
        $schema = LocationFixture::iranSchema();
        $countryType = $schema->types->firstWhere('key', 'country');
        $v1Location = \App\Models\Location::factory()->create([
            'location_schema_id' => $schema->id,
            'location_type_id' => $countryType->id,
            'country_code' => 'IR',
            'name' => 'Iran legacy',
            'canonical_name' => 'Iran legacy',
            'localized_names' => ['fa' => 'ایران قدیمی'],
            'level' => 'country',
            'status' => 'active',
        ]);
        $v2Location = \App\Models\Location::factory()->create([
            'location_schema_id' => $schema->id,
            'location_type_id' => $countryType->id,
            'country_code' => 'IR',
            'name' => 'ایران',
            'canonical_name' => 'ایران',
            'localized_names' => ['fa' => 'ایران'],
            'level' => 'country',
            'status' => 'active',
        ]);

        $global = GovernanceArea::factory()->official()->create([
            'key' => 'earthcoop-global',
            'parent_id' => null,
            'governance_type' => 'global',
            'canonical_name' => 'EarthCoop Global',
            'localized_names' => ['fa' => 'جهانی'],
            'status' => 'active',
        ]);
        $asia = GovernanceArea::factory()->official()->create([
            'key' => 'earthcoop-continent-asia',
            'parent_id' => $global->id,
            'governance_type' => 'continent',
            'canonical_name' => 'Asia',
            'localized_names' => ['fa' => 'آسیا'],
            'status' => 'active',
        ]);
        $v1 = GovernanceArea::factory()->official()->create([
            'key' => 'ir-reference-v1-country',
            'parent_id' => $asia->id,
            'country_code' => 'IR',
            'governance_type' => 'country',
            'canonical_name' => 'Iran legacy',
            'localized_names' => ['fa' => 'ایران قدیمی'],
            'metadata' => ['reference_topology' => true, 'source' => 'earthcoop-reference-governance', 'dataset_version' => 'v1'],
            'status' => 'active',
        ]);
        $v2 = GovernanceArea::factory()->official()->create([
            'key' => 'ir-reference-v2-ir-1404-1',
            'parent_id' => $asia->id,
            'country_code' => 'IR',
            'governance_type' => 'country',
            'canonical_name' => 'ایران',
            'localized_names' => ['fa' => 'ایران'],
            'metadata' => ['reference_topology' => true, 'source' => 'earthcoop-reference-governance', 'dataset_version' => 'v2'],
            'status' => 'active',
        ]);
        $v1->locations()->attach($v1Location->id);
        $v2->locations()->attach($v2Location->id);

        $response = $this->getJson('/location/residence/options/governance/'.$asia->id.'/children')->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.identity', 'location:'.$v2Location->id);
        $response->assertJsonPath('data.0.label', 'ایران');
    }

    public function test_profile_hydration_path_prepends_continent_but_not_global_or_type_titles(): void
    {
        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood'],
            ['Iran', 'Mazandaran', 'Sari County', 'Central Section', 'Sari', 'Urban Region 1', 'Reference Neighborhood'],
        );
        [, $asia] = $this->governancePathFor($path->first());
        $user = User::factory()->create();
        app(ResidenceService::class)->setInitialPrimaryResidence($user, $path->last(), ['source' => 'test']);

        $response = $this->actingAs($user)->get('/profile/edit');

        $response->assertOk();
        $response->assertSee('governance:'.$asia->id, false);
        $response->assertSee('location:'.$path->first()->id, false);
        $response->assertDontSee('data-country-code="IR"', false);
    }

    private function governancePathFor($country): array
    {
        $global = GovernanceArea::factory()->official()->create([
            'parent_id' => null, 'governance_type' => 'global', 'canonical_name' => 'EarthCoop Global',
            'localized_names' => ['fa' => 'جهانی'], 'status' => 'active',
        ]);
        $asia = GovernanceArea::factory()->official()->create([
            'parent_id' => $global->id, 'governance_type' => 'continent', 'canonical_name' => 'Asia',
            'localized_names' => ['fa' => 'آسیا'], 'status' => 'active',
        ]);
        $iran = GovernanceArea::factory()->official()->create([
            'parent_id' => $asia->id, 'governance_type' => 'country', 'canonical_name' => 'Iran',
            'localized_names' => ['fa' => 'ایران'], 'status' => 'active',
        ]);
        $iran->locations()->attach($country->id);

        return [$global, $asia, $iran];
    }
}
