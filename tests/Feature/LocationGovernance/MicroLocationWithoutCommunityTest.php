<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\User;
use App\Services\LocationGovernance\CommunityCreationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class MicroLocationWithoutCommunityTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_complex_and_building_can_exist_without_community_area(): void
    {
        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath($schema, [
            'country',
            'province',
            'county',
            'section',
            'city',
            'urban_region',
            'neighborhood',
            'street',
            'complex',
            'building',
        ]);

        $complex = $path->firstWhere('level', 'complex');
        $building = $path->firstWhere('level', 'building');

        $this->assertSame('active', $complex->status);
        $this->assertSame('active', $building->status);
        $this->assertSame(0, GovernanceArea::query()->where('area_kind', 'community')->count());
        $this->assertCount(0, $complex->governanceAreas()->where('area_kind', 'community')->get());
        $this->assertCount(0, $building->governanceAreas()->where('area_kind', 'community')->get());
    }

    public function test_community_creation_policy_accepts_active_micro_locations_but_rejects_non_micro_and_inactive_locations(): void
    {
        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath($schema, [
            'country',
            'province',
            'county',
            'section',
            'city',
            'urban_region',
            'neighborhood',
            'street',
            'complex',
        ]);

        $actor = User::factory()->create();
        $city = $path->firstWhere('level', 'city');
        $complex = $path->firstWhere('level', 'complex');
        $policy = app(CommunityCreationPolicy::class);

        $this->assertTrue($policy->mayCreateFor($complex, $actor));
        $this->assertFalse($policy->mayCreateFor($city, $actor));

        $complex->update(['status' => 'inactive']);
        $this->assertFalse($policy->mayCreateFor($complex->fresh(), $actor));
    }
}
