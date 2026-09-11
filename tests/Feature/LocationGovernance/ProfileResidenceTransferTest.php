<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\User;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\RegistrationFixture;
use Tests\TestCase;

class ProfileResidenceTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_profile_edit_renders_for_a_user_with_primary_residence_and_no_legacy_address(): void
    {
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
        ]);

        ['endpoint' => $city] = RegistrationFixture::urbanSari();
        $user = User::factory()->create();

        app(ResidenceService::class)->setInitialPrimaryResidence($user, $city, [
            'source' => 'test_registration',
        ]);

        $this->assertDatabaseMissing('addresses', ['user_id' => $user->id]);

        $response = $this->actingAs($user)->get('/profile/edit');

        $response->assertOk();
        $response->assertSee('data-location-selector-context="profile"', false);
        $response->assertSee('name="location_id"', false);
    }
}
