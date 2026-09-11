<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\User;
use App\Models\UserLocationRelationship;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class NonResidenceRelationshipVotingTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_and_study_locations_do_not_create_parallel_official_governance_scope(): void
    {
        $user = User::factory()->create();
        $schema = LocationFixture::iranSchema();
        $home = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last();
        $work = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last();
        $study = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last();

        $homeArea = GovernanceArea::factory()->official()->create(['key' => 'home-area']);
        $workArea = GovernanceArea::factory()->official()->create(['key' => 'work-area']);
        $studyArea = GovernanceArea::factory()->official()->create(['key' => 'study-area']);
        $homeArea->locations()->attach($home->id);
        $workArea->locations()->attach($work->id);
        $studyArea->locations()->attach($study->id);

        app(ResidenceService::class)->setInitialPrimaryResidence($user, $home, ['source' => 'registration']);
        UserLocationRelationship::factory()->work()->create(['user_id' => $user->id, 'location_id' => $work->id]);
        UserLocationRelationship::factory()->study()->create(['user_id' => $user->id, 'location_id' => $study->id]);

        $officialAreaIds = app(ResidenceService::class)
            ->officialGovernanceAreasFor($user)
            ->pluck('id')
            ->all();

        $this->assertSame([$homeArea->id], $officialAreaIds);
        $this->assertNotContains($workArea->id, $officialAreaIds);
        $this->assertNotContains($studyArea->id, $officialAreaIds);
    }

    public function test_without_current_primary_residence_no_official_geographic_scope_is_granted(): void
    {
        $user = User::factory()->create();
        $schema = LocationFixture::iranSchema();
        $work = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last();
        $area = GovernanceArea::factory()->official()->create(['key' => 'work-only-area']);
        $area->locations()->attach($work->id);
        UserLocationRelationship::factory()->work()->create(['user_id' => $user->id, 'location_id' => $work->id]);

        $this->assertTrue(app(ResidenceService::class)->officialGovernanceAreasFor($user)->isEmpty());
    }
}
