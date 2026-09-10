<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityElectionBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_community_areas_are_outside_formal_official_election_topology_by_default(): void
    {
        $official = GovernanceArea::factory()->official()->create([
            'status' => 'active',
            'parent_id' => null,
        ]);
        $community = GovernanceArea::factory()->community()->create([
            'status' => 'active',
            'parent_id' => $official->id,
        ]);

        $topology = app(\App\Contracts\Governance\OfficialGovernanceTopology::class);

        $this->assertNull($topology->parentOf($community));
        $this->assertFalse($topology->childrenOf($official)->contains('id', $community->id));
    }

    public function test_formal_topology_never_promotes_community_children_into_official_chain(): void
    {
        $root = GovernanceArea::factory()->official()->create(['status' => 'active']);
        $officialChild = GovernanceArea::factory()->official()->create([
            'status' => 'active',
            'parent_id' => $root->id,
        ]);
        GovernanceArea::factory()->community()->create([
            'status' => 'active',
            'parent_id' => $root->id,
        ]);

        $topology = app(\App\Contracts\Governance\OfficialGovernanceTopology::class);

        $children = $topology->childrenOf($root);
        $this->assertCount(1, $children);
        $this->assertSame($officialChild->id, $children->first()?->id);
    }
}
