<?php

namespace Tests\Support\LocationGovernance;

use App\Enums\Membership\GroupCreationMode;
use App\Models\AgeGroup;
use App\Models\ExperienceField;
use App\Models\GovernanceArea;
use App\Models\GroupCreationPolicy;
use App\Models\MembershipDimension;
use App\Models\OccupationalField;
use App\Models\User;
use App\Models\UserLocationRelationship;

final class MembershipFixture
{
    public static function canonicalUser(array $modes = []): array
    {
        $registration = RegistrationFixture::urbanSari();
        $endpoint = $registration['endpoint'];

        $user = User::factory()->create([
            'birth_date' => now()->subYears(30)->toDateString(),
            'gender' => 'male',
        ]);

        UserLocationRelationship::create([
            'user_id' => $user->id,
            'location_id' => $endpoint->id,
            'relationship_type' => 'primary_residence',
            'started_at' => now()->subDay(),
        ]);

        $area = GovernanceArea::create([
            'key' => 'ir.sari',
            'country_code' => 'IR',
            'governance_type' => 'city',
            'area_kind' => 'official',
            'canonical_name' => 'Sari',
            'rank' => 10,
            'status' => 'active',
        ]);
        $area->locations()->attach($endpoint->id);

        $profession = OccupationalField::create(['name' => 'Engineering', 'status' => 1]);
        $specialty = ExperienceField::create(['name' => 'Software', 'status' => 1]);
        $user->occupationalFields()->attach($profession->id);
        $user->experienceFields()->attach($specialty->id);

        AgeGroup::create(['title' => '25-34', 'min_age' => 25, 'max_age' => 34]);

        foreach (['public', 'profession', 'specialty', 'age', 'gender'] as $key) {
            $dimension = MembershipDimension::create([
                'key' => $key,
                'canonical_name' => ucfirst($key),
                'resolver_key' => $key,
                'status' => 'active',
            ]);

            GroupCreationPolicy::create([
                'membership_dimension_id' => $dimension->id,
                'governance_area_id' => null,
                'mode' => $modes[$key] ?? GroupCreationMode::Automatic,
                'threshold' => ($modes[$key] ?? null) === GroupCreationMode::Threshold ? 20 : null,
                'policy_version' => 'test-v1',
            ]);
        }

        return compact('user', 'area', 'endpoint');
    }
}
