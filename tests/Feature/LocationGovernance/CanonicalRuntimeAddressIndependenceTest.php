<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\Address;
use App\Models\ExperienceField;
use App\Models\User;
use App\Models\UserExperience;
use App\Services\ProfileCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class CanonicalRuntimeAddressIndependenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_profile_completion_does_not_require_legacy_address_row(): void
    {
        config()->set('location-governance.runtime_enabled', true);
        config()->set('location-governance.registration_enabled', true);

        $user = $this->canonicalUserWithoutLegacyAddress();

        $this->assertFalse(Address::where('user_id', $user->id)->exists());
        $this->assertTrue(app(ProfileCompletionService::class)->isComplete($user));
    }

    public function test_legacy_profile_completion_still_requires_address_when_canonical_runtime_is_disabled(): void
    {
        config()->set('location-governance.runtime_enabled', false);
        config()->set('location-governance.registration_enabled', false);

        $user = $this->canonicalUserWithoutLegacyAddress();

        $this->assertFalse(app(ProfileCompletionService::class)->isComplete($user));
    }

    private function canonicalUserWithoutLegacyAddress(): User
    {
        $schema = LocationFixture::iranSchema();
        $residence = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood',
        ])->last();

        $user = User::factory()->create([
            'first_name' => 'Canonical',
            'last_name' => 'Resident',
            'gender' => 'male',
            'national_id' => '0012345678',
            'phone' => '09120000000',
        ]);

        $experienceField = ExperienceField::create([
            'name' => 'Canonical runtime test field',
            'status' => 1,
        ]);
        $userExperience = new UserExperience();
        $userExperience->user_id = $user->id;
        $userExperience->experience_field_id = $experienceField->id;
        $userExperience->save();

        $user->locationRelationships()->create([
            'location_id' => $residence->id,
            'relationship_type' => 'primary_residence',
            'started_at' => now(),
        ]);

        return $user->fresh();
    }
}
