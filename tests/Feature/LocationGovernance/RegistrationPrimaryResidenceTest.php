<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\Support\LocationGovernance\RegistrationFixture;
use Tests\TestCase;

class RegistrationPrimaryResidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_registration_accepts_urban_sari_as_a_residence_endpoint(): void
    {
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
        ]);

        ['endpoint' => $city] = RegistrationFixture::urbanSari();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/register/step3', [
            'location_id' => $city->id,
        ]);

        $response->assertRedirect(route('home'));
        $this->assertDatabaseHas('user_location_relationships', [
            'user_id' => $user->id,
            'location_id' => $city->id,
            'relationship_type' => 'primary_residence',
            'explicit_transfer' => 0,
        ]);
    }

    public function test_canonical_registration_accepts_a_rural_village_at_variable_depth(): void
    {
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
        ]);

        ['endpoint' => $village] = RegistrationFixture::ruralVillage();
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

    public function test_village_does_not_require_a_neighborhood_beneath_it(): void
    {
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
        ]);

        ['endpoint' => $village] = RegistrationFixture::villageWithoutNeighborhood();
        $this->assertFalse($village->children()->exists());

        $user = User::factory()->create();
        $response = $this->actingAs($user)->post('/register/step3', [
            'location_id' => $village->id,
        ]);

        $response->assertRedirect(route('home'));
        $this->assertDatabaseHas('user_location_relationships', [
            'user_id' => $user->id,
            'location_id' => $village->id,
            'relationship_type' => 'primary_residence',
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

        ['endpoint' => $city] = RegistrationFixture::urbanSari();
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
