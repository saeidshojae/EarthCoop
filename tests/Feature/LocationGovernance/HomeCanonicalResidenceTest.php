<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\Address;
use App\Models\User;
use App\Models\UserExperience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class HomeCanonicalResidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_resident_without_legacy_address_can_render_home(): void
    {
        config()->set('location-governance.runtime_enabled', true);
        config()->set('location-governance.registration_enabled', true);

        $user = $this->canonicalUserWithoutLegacyAddress();

        $this->assertFalse(Address::where('user_id', $user->id)->exists());

        $response = $this->actingAs($user)->get('/home');

        $response->assertOk();
        $response->assertViewIs('home');
    }

    public function test_legacy_mode_without_address_keeps_step_three_rollback_gate(): void
    {
        config()->set('location-governance.runtime_enabled', false);
        config()->set('location-governance.registration_enabled', false);

        $user = $this->canonicalUserWithoutLegacyAddress();

        $this->actingAs($user)
            ->get('/home')
            ->assertRedirect('/register/step3');
    }

    private function canonicalUserWithoutLegacyAddress(): User
    {
        $schema = LocationFixture::iranSchema();
        $residence = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood',
        ])->last();

        $user = User::factory()->create([
            'first_name' => 'Home',
            'last_name' => 'Resident',
            'gender' => 'male',
            'national_id' => '0012345679',
            'phone' => '09120000001',
        ]);

        UserExperience::factory()->create(['user_id' => $user->id]);
        $user->locationRelationships()->create([
            'location_id' => $residence->id,
            'relationship_type' => 'primary_residence',
            'started_at' => now(),
        ]);

        return $user->fresh();
    }
}
