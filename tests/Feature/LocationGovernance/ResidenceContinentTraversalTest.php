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
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
        ]);
        app()->setLocale('fa');
    }

    public function test_residence_root_starts_at_continent_and_bridges_country_to_canonical_location(): void
    {
        $schema = LocationFixture::iranSchema();
        $country = LocationFixture::createPath($schema, ['country'], ['Iran'])->first();
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
