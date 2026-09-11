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
use App\Services\Membership\AgeDimensionResolver;
use App\Services\Membership\GenderDimensionResolver;
use App\Services\Membership\ProfessionDimensionResolver;
use App\Services\Membership\PublicDimensionResolver;
use App\Services\Membership\SpecialtyDimensionResolver;

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

        $resolverClasses = [
            'public' => PublicDimensionResolver::class,
            'profession' => ProfessionDimensionResolver::class,
            'specialty' => SpecialtyDimensionResolver::class,
            'age' => AgeDimensionResolver::class,
            'gender' => GenderDimensionResolver::class,
        ];

        foreach ($resolverClasses as $key => $resolverClass) {
            $dimension = MembershipDimension::create([
                'key' => $key,
                'name' => ucfirst($key),
                'resolver_class' => $resolverClass,
                'enabled' => true,
            ]);

            $mode = $modes[$key] ?? GroupCreationMode::Automatic;
            GroupCreationPolicy::create([
                'membership_dimension_id' => $dimension->id,
                'governance_area_id' => null,
                'mode' => $mode,
                'threshold' => $mode === GroupCreationMode::Threshold ? 20 : null,
                'enabled' => true,
                'metadata' => ['policy_version' => 'test-v1'],
            ]);
        }

        return compact('user', 'area', 'endpoint');
    }
}
