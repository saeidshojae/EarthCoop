<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\User;
use App\Services\LocationGovernance\ResidenceService;
use App\Services\ProfileCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class ProfileCompletionCanonicalResidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_primary_residence_completes_spatial_requirement_when_canonical_registration_is_enabled(): void
    {
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
        ]);

        $user = User::factory()->create([
            'first_name' => 'Saeed',
            'last_name' => 'Test',
            'gender' => 'male',
            'national_id' => '1234567890',
            'phone' => '09120000000',
        ]);

        $experienceId = DB::table('experience_fields')->insertGetId([
            'name' => 'Test Experience',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_experience_field')->insert([
            'user_id' => $user->id,
            'experience_field_id' => $experienceId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'rural_district', 'village'],
            ['Iran', 'Mazandaran', 'Sari County', 'Chahardangeh', 'Poshtkuh', 'A Village'],
        );
        app(ResidenceService::class)->setInitialPrimaryResidence($user, $path->last(), [
            'source' => 'test',
        ]);

        $this->assertTrue(app(ProfileCompletionService::class)->isComplete($user->fresh()));
    }

    public function test_canonical_residence_does_not_change_legacy_completion_semantics_when_flag_is_disabled(): void
    {
        config(['location-governance.registration_enabled' => false]);

        $user = User::factory()->create([
            'first_name' => 'Saeed',
            'last_name' => 'Test',
            'gender' => 'male',
            'national_id' => '1234567890',
            'phone' => '09120000000',
        ]);

        $experienceId = DB::table('experience_fields')->insertGetId([
            'name' => 'Test Experience',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_experience_field')->insert([
            'user_id' => $user->id,
            'experience_field_id' => $experienceId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'city'],
            ['Iran', 'Mazandaran', 'Sari County', 'Central Section', 'Sari'],
        );
        app(ResidenceService::class)->setInitialPrimaryResidence($user, $path->last(), [
            'source' => 'test',
        ]);

        $this->assertFalse(app(ProfileCompletionService::class)->isComplete($user->fresh()));
    }
}
