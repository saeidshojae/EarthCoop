<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\User;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class ProfileCanonicalResidenceHydrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
        ]);
    }

    public function test_profile_edit_exposes_complete_canonical_residence_path_without_pending_proposal(): void
    {
        app()->setLocale('fa');

        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood'],
            ['Iran', 'Mazandaran', 'Sari County', 'Central Section', 'Sari', 'Sari Urban Region 1', 'Sari Reference Neighborhood'],
        );

        [$country, $province, $county, $section, $city, $region, $neighborhood] = $path->all();
        $neighborhood->update([
            'localized_names' => ['fa' => 'محله مرجع ساری'],
        ]);

        $user = User::factory()->create();

        app(ResidenceService::class)->setInitialPrimaryResidence($user, $neighborhood, [
            'source' => 'canonical-profile-hydration-test',
        ]);

        $response = $this->actingAs($user)->get(route('profile.edit'));

        $response->assertOk();
        $response->assertSee('data-location-current-id="'.$neighborhood->id.'"', false);
        $response->assertSee('name="location_id" value="'.$neighborhood->id.'" data-location-id', false);
        $response->assertSee('name="location_proposal_id" value="" data-location-proposal-id', false);
        $response->assertSee('محله مرجع ساری');
        $response->assertDontSee('Sari Reference Neighborhood');
        $response->assertSeeInOrder([
            'location:'.$country->id,
            'location:'.$province->id,
            'location:'.$county->id,
            'location:'.$section->id,
            'location:'.$city->id,
            'location:'.$region->id,
            'location:'.$neighborhood->id,
        ], false);
    }
}
