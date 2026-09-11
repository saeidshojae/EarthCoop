<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\GovernanceCapabilityPolicy;
use App\Models\User;
use App\Services\LocationGovernance\CommunityAreaService;
use App\Services\LocationGovernance\GovernanceCapabilityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class CommunityAreaCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_community_area_is_created_on_demand_is_idempotent_and_remains_separate_from_official_governance(): void
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

        $neighborhood = $path->firstWhere('level', 'neighborhood');
        $complex = $path->firstWhere('level', 'complex');
        $official = GovernanceArea::factory()->official()->create([
            'key' => 'official-neighborhood-'.$neighborhood->id,
            'country_code' => 'IR',
            'governance_type' => 'local',
            'canonical_name' => 'Official neighborhood',
        ]);
        $official->locations()->attach($neighborhood->id);

        $actor = User::factory()->create();
        $service = app(CommunityAreaService::class);

        $first = $service->createFor($complex, $actor);
        $second = $service->createFor($complex, $actor);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('community', $first->area_kind);
        $this->assertSame($official->id, $first->parent_id);
        $this->assertNotSame($official->id, $first->id);
        $this->assertTrue($first->locations()->whereKey($complex->id)->exists());
        $this->assertSame(1, GovernanceArea::query()->where('area_kind', 'community')->count());
        $this->assertSame($actor->id, data_get($first->metadata, 'created_by_user_id'));
    }

    public function test_community_area_inherits_policy_driven_capabilities_without_gaining_systemic_election_by_default(): void
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
        $complex = $path->firstWhere('level', 'complex');

        GovernanceCapabilityPolicy::create([
            'scope' => 'default',
            'capabilities' => [
                'chat' => true,
                'polls' => true,
                'systemic_elections' => false,
            ],
        ]);
        GovernanceCapabilityPolicy::create([
            'scope' => 'country_type',
            'country_code' => 'IR',
            'governance_type' => 'community',
            'capabilities' => [
                'projects' => true,
            ],
        ]);

        $community = app(CommunityAreaService::class)->createFor($complex, User::factory()->create());
        $capabilities = app(GovernanceCapabilityResolver::class)->capabilities($community);

        $this->assertSame('community', $community->governance_type);
        $this->assertTrue($capabilities->enabled('chat'));
        $this->assertTrue($capabilities->enabled('polls'));
        $this->assertTrue($capabilities->enabled('projects'));
        $this->assertFalse($capabilities->enabled('systemic_elections'));
    }
}
