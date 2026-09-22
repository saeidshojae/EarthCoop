<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\User;
use App\Services\LocationGovernance\LocationStructureClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\Support\LocationGovernance\MembershipFixture;
use Tests\Support\LocationGovernance\RegistrationFixture;
use Tests\TestCase;

class RegistrationPrimaryResidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_steps_require_an_authenticated_user(): void
    {
        $this->get('/register/step3')->assertRedirect(route('login'));
        $this->post('/register/step3', [])->assertRedirect(route('login'));
    }

    public function test_canonical_registration_accepts_urban_sari_as_a_residence_endpoint(): void
    {
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
        ]);

        ['endpoint' => $city] = RegistrationFixture::urbanSari();
        $user = User::factory()->create();
        $claims = $this->cityWithoutRegionOrNeighborhoodClaims($city, $user);

        $response = $this->actingAs($user)->post('/register/step3', [
            'location_id' => $city->id,
            'location_structure_claim_ids' => $claims,
        ]);

        $response->assertRedirect(route('home'));
        $this->assertDatabaseHas('user_location_relationships', [
            'user_id' => $user->id,
            'location_id' => $city->id,
            'relationship_type' => 'primary_residence',
            'explicit_transfer' => 0,
        ]);
    }

    public function test_registration_requires_deepest_available_governance_base_before_micro_detail(): void
    {
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
        ]);

        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood',
        ]);
        $city = $path->firstWhere('level', 'city');
        $neighborhood = $path->last();
        $user = User::factory()->create();

        $this->actingAs($user)->from('/register/step3')->post('/register/step3', [
            'location_id' => $city->id,
        ])->assertRedirect('/register/step3')->assertSessionHasErrors('location_id');

        $this->actingAs($user)->post('/register/step3', [
            'location_id' => $neighborhood->id,
        ])->assertRedirect(route('home'));

        $this->assertDatabaseHas('user_location_relationships', [
            'user_id' => $user->id,
            'location_id' => $neighborhood->id,
            'relationship_type' => 'primary_residence',
        ]);
    }

    public function test_canonical_registration_materializes_system_groups_immediately_when_stage_c_groups_are_enabled(): void
    {
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
            'location-governance.groups_enabled' => true,
        ]);

        ['user' => $user, 'endpoint' => $endpoint] = MembershipFixture::canonicalUser();
        $user->locationRelationships()->delete();
        $claims = $this->cityWithoutRegionOrNeighborhoodClaims($endpoint, $user);

        $response = $this->actingAs($user)->post('/register/step3', [
            'location_id' => $endpoint->id,
            'location_structure_claim_ids' => $claims,
        ]);

        $response->assertRedirect(route('home'));

        $memberships = $user->fresh()->groups()
            ->wherePivot('status', 1)
            ->whereNotNull('groups.governance_area_id')
            ->get();

        $this->assertSame(5, $memberships->count());
        $this->assertSame(
            ['age', 'gender', 'profession', 'public', 'specialty'],
            $memberships->pluck('dimension_key')->sort()->values()->all(),
        );
    }

    public function test_canonical_registration_accepts_a_rural_village_at_variable_depth(): void
    {
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
        ]);

        ['endpoint' => $village] = RegistrationFixture::ruralVillage();
        $user = User::factory()->create();
        $claim = app(LocationStructureClaimService::class)->findOrCreateOpenClaim($village, 'no_neighborhood', $user);

        $response = $this->actingAs($user)->post('/register/step3', [
            'location_id' => $village->id,
            'location_structure_claim_ids' => [$claim->id],
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
        $claim = app(LocationStructureClaimService::class)->findOrCreateOpenClaim($village, 'no_neighborhood', $user);
        $response = $this->actingAs($user)->post('/register/step3', [
            'location_id' => $village->id,
            'location_structure_claim_ids' => [$claim->id],
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
    /** @return array<int, int> */
    private function cityWithoutRegionOrNeighborhoodClaims($city, User $user): array
    {
        $service = app(LocationStructureClaimService::class);

        return [
            $service->findOrCreateOpenClaim($city, 'no_urban_region', $user)->id,
            $service->findOrCreateOpenClaim($city, 'no_neighborhood', $user)->id,
        ];
    }

}
