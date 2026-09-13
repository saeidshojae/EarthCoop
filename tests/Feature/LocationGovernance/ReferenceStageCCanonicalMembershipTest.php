<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\AgeGroup;
use App\Models\ExperienceField;
use App\Models\LocationExternalId;
use App\Models\OccupationalField;
use App\Models\User;
use App\Services\LocationGovernance\GovernanceResolver;
use App\Services\LocationGovernance\ResidenceService;
use Database\Seeders\LocationGovernanceBootstrapSeeder;
use Database\Seeders\StageCCanonicalGroupPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ReferenceStageCCanonicalMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_iran_reference_neighborhood_materializes_minimum_81_canonical_memberships(): void
    {
        $this->seed(LocationGovernanceBootstrapSeeder::class);

        $this->assertSame(0, Artisan::call('location:reference-import', [
            'country' => 'IR',
            '--dataset-version' => 'v1',
            '--apply' => true,
        ]));
        $this->assertSame(0, Artisan::call('location-governance:reference-topology', [
            'country' => 'IR',
            '--dataset-version' => 'v1',
            '--apply' => true,
        ]));
        $this->seed(StageCCanonicalGroupPolicySeeder::class);

        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
            'location-governance.groups_enabled' => true,
        ]);

        $user = User::factory()->create([
            'birth_date' => now()->subYears(30)->toDateString(),
            'gender' => 'male',
        ]);

        $professionRoot = OccupationalField::create(['name' => 'فرهنگیان', 'status' => 1]);
        $professionMiddle = OccupationalField::create(['name' => 'معلمان ابتدایی', 'parent_id' => $professionRoot->id, 'status' => 1]);
        $professionLeaf = OccupationalField::create(['name' => 'معلمان پایه اول', 'parent_id' => $professionMiddle->id, 'status' => 1]);
        $user->occupationalFields()->attach($professionLeaf->id);

        $specialtyRoot = ExperienceField::create(['name' => 'آموزش', 'status' => 1]);
        $specialtyMiddle = ExperienceField::create(['name' => 'آموزش ابتدایی', 'parent_id' => $specialtyRoot->id, 'status' => 1]);
        $specialtyLeaf = ExperienceField::create(['name' => 'آموزش پایه اول', 'parent_id' => $specialtyMiddle->id, 'status' => 1]);
        $user->experienceFields()->attach($specialtyLeaf->id);

        AgeGroup::create(['title' => '25-34', 'min_age' => 25, 'max_age' => 34]);

        $residence = LocationExternalId::query()
            ->where('source', 'earthcoop-reference')
            ->where('dataset_version', 'v1')
            ->where('external_id', 'IR-SARI-NBH-01')
            ->firstOrFail()
            ->location;

        $areas = app(GovernanceResolver::class)->officialAreasForResidence($residence);
        $this->assertCount(9, $areas);
        $this->assertSame([
            'local', 'urban_region', 'city', 'section', 'county', 'province', 'country', 'continent', 'global',
        ], $areas->pluck('governance_type')->values()->all());

        app(ResidenceService::class)->setInitialPrimaryResidence($user, $residence, [
            'source' => 'reference_stage_c_membership_test',
        ]);

        $groups = $user->groups()
            ->wherePivot('status', 1)
            ->whereNotNull('groups.governance_area_id')
            ->get();

        $this->assertCount(81, $groups);
        $this->assertSame(9, $groups->filter(fn ($group) => (int) $group->pivot->role === 1)->count());
        $this->assertSame(72, $groups->filter(fn ($group) => (int) $group->pivot->role === 0)->count());

        $this->assertSame([
            'age' => 9,
            'gender' => 9,
            'profession' => 27,
            'public' => 9,
            'specialty' => 27,
        ], $groups->countBy('dimension_key')->sortKeys()->all());
    }
}
