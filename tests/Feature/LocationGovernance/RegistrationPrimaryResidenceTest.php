<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class RegistrationPrimaryResidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_registration_accepts_a_variable_depth_residence_endpoint(): void
    {
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
        ]);

        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'rural_district', 'village'],
            ['Iran', 'Mazandaran', 'Sari County', 'Chahardangeh', 'Poshtkuh', 'A Village'],
        );
        $village = $path->last();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/register/step3', [
            'location_id' => $village->id,
        ]);

        $response->assertRedirect(route('home'));
        $this->assertDatabaseHas('user_location_relationships', [
            'user_id' => $user->id,
            'location_id' => $village->id,
            'relationship_type' => 'primary_residence',
            'explicit_transfer' => 0,
        ]);
    }

    public function test_canonical_registration_rejects_a_non_endpoint_even_when_the_id_exists(): void
    {
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
        ]);

        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section'],
            ['Iran', 'Mazandaran', 'Sari County', 'Central Section'],
        );
        $section = $path->last();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->from('/register/step3')->post('/register/step3', [
            'location_id' => $section->id,
        ]);

        $response->assertRedirect('/register/step3');
        $response->assertSessionHasErrors('location_id');
        $this->assertDatabaseMissing('user_location_relationships', [
            'user_id' => $user->id,
            'relationship_type' => 'primary_residence',
        ]);
    }

    public function test_registration_flag_off_preserves_the_legacy_fixed_field_contract(): void
    {
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => false,
        ]);

        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'city'],
            ['Iran', 'Mazandaran', 'Sari County', 'Central Section', 'Sari'],
        );
        $city = $path->last();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->from('/register/step3')->post('/register/step3', [
            'location_id' => $city->id,
        ]);

        $response->assertRedirect('/register/step3');
        $response->assertSessionHasErrors([
            'continent_id',
            'country_id',
            'province_id',
            'county_id',
            'section_id',
        ]);
        $this->assertDatabaseMissing('user_location_relationships', [
            'user_id' => $user->id,
            'relationship_type' => 'primary_residence',
        ]);
    }
}
