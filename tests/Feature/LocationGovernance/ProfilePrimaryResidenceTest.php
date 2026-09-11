<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\User;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class ProfilePrimaryResidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_location_update_transfers_primary_residence_when_canonical_registration_is_enabled(): void
    {
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
        ]);

        $schema = LocationFixture::iranSchema();
        $oldPath = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'city'],
            ['Iran', 'Mazandaran', 'Sari County', 'Central Section', 'Sari'],
        );
        $newPath = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'rural_district', 'village'],
            ['Iran 2', 'Mazandaran 2', 'Sari County 2', 'Chahardangeh', 'Poshtkuh', 'A Village'],
        );

        $oldLocation = $oldPath->last();
        $newLocation = $newPath->last();
        $user = User::factory()->create();
        app(ResidenceService::class)->setInitialPrimaryResidence($user, $oldLocation, ['source' => 'test']);

        $response = $this->actingAs($user)->put(route('profile.update.address'), [
            'location_id' => $newLocation->id,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('user_location_relationships', [
            'user_id' => $user->id,
            'location_id' => $oldLocation->id,
            'relationship_type' => 'primary_residence',
            'explicit_transfer' => 0,
        ]);
        $this->assertNotNull(
            $user->locationRelationships()
                ->where('location_id', $oldLocation->id)
                ->where('relationship_type', 'primary_residence')
                ->value('ended_at')
        );
        $this->assertDatabaseHas('user_location_relationships', [
            'user_id' => $user->id,
            'location_id' => $newLocation->id,
            'relationship_type' => 'primary_residence',
            'explicit_transfer' => 1,
        ]);
        $this->assertNull(
            $user->locationRelationships()
                ->where('location_id', $newLocation->id)
                ->where('relationship_type', 'primary_residence')
                ->value('ended_at')
        );
    }

    public function test_profile_location_update_rejects_non_endpoint_in_canonical_mode(): void
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

        $response = $this->actingAs($user)->from(route('profile.edit'))->put(route('profile.update.address'), [
            'location_id' => $section->id,
        ]);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHasErrors('location_id');
        $this->assertDatabaseMissing('user_location_relationships', [
            'user_id' => $user->id,
            'relationship_type' => 'primary_residence',
        ]);
    }
}
