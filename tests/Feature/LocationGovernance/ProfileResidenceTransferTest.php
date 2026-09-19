<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\User;
use App\Services\LocationGovernance\LocationStructureClaimService;
use Tests\Support\LocationGovernance\LocationFixture;
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

    public function test_profile_transfer_preserves_selected_ancestor_structural_claim_and_committed_support(): void
    {
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
        ]);

        $schema = LocationFixture::iranSchema();
        $oldHome = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood'])->last();
        $newPath = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city']);
        $city = $newPath->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $street = \App\Models\Location::query()->create([
            'parent_id' => $city->id,
            'location_schema_id' => $schema->id,
            'location_type_id' => $streetType->id,
            'country_code' => 'IR',
            'canonical_name' => 'خیابان انتقال پروفایل',
            'status' => 'active',
        ]);
        $user = User::factory()->create();
        app(ResidenceService::class)->setInitialPrimaryResidence($user, $oldHome, ['source' => 'test']);
        $claim = app(LocationStructureClaimService::class)->findOrCreateOpenClaim($city, 'no_urban_region', $user);

        $this->actingAs($user)->put(route('profile.update.address'), [
            'location_id' => $street->id,
            'location_structure_claim_ids' => [$claim->id],
        ])->assertSessionHasNoErrors();

        $current = $user->fresh()->locationRelationships()->where('relationship_type', 'primary_residence')->whereNull('ended_at')->sole();
        $this->assertSame($street->id, $current->location_id);
        $this->assertSame([$claim->id], $current->metadata['structural_claim_ids']);
        $this->assertTrue($claim->fresh()->evidence()->where('user_id', $user->id)->exists());
    }


    public function test_profile_can_commit_new_structural_claim_without_changing_current_location(): void
    {
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
        ]);

        $schema = LocationFixture::iranSchema();
        $city = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last();
        $user = User::factory()->create();
        app(ResidenceService::class)->setInitialPrimaryResidence($user, $city, ['source' => 'test']);
        $claim = app(LocationStructureClaimService::class)->findOrCreateOpenClaim($city, 'no_urban_region', $user);

        $this->actingAs($user)->put(route('profile.update.address'), [
            'location_id' => $city->id,
            'location_structure_claim_ids' => [$claim->id],
        ])->assertSessionHasNoErrors();

        $current = $user->fresh()->locationRelationships()
            ->where('relationship_type', 'primary_residence')
            ->whereNull('ended_at')
            ->sole();

        $this->assertSame($city->id, $current->location_id);
        $this->assertSame([$claim->id], $current->metadata['structural_claim_ids']);
        $this->assertTrue($claim->fresh()->evidence()->where('user_id', $user->id)->exists());
    }

}
