<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficialElectionGovernanceTopologyTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_governance_topology_contract_and_additive_election_scope_exist(): void
    {
        $this->assertTrue(interface_exists(\App\Contracts\Governance\OfficialGovernanceTopology::class));
        $this->assertTrue(class_exists(\App\Services\Elections\GovernanceElectionTopology::class));

        $path = database_path('migrations/2026_09_10_000008_add_governance_scope_to_elections.php');
        $this->assertFileExists($path);
        $source = file_get_contents($path);

        $this->assertStringContainsString('governance_area_id', $source);
        $this->assertStringNotContainsString("dropColumn('group_id')", $source);
        $this->assertStringNotContainsString('dropColumn("group_id")', $source);
    }

    public function test_formal_topology_traverses_active_official_governance_parent_and_children(): void
    {
        $root = GovernanceArea::factory()->official()->create([
            'parent_id' => null,
            'status' => 'active',
            'rank' => 1000,
        ]);
        $child = GovernanceArea::factory()->official()->create([
            'parent_id' => $root->id,
            'status' => 'active',
            'rank' => 900,
        ]);
        GovernanceArea::factory()->official()->create([
            'parent_id' => $root->id,
            'status' => 'inactive',
            'rank' => 900,
        ]);

        $topology = app(\App\Contracts\Governance\OfficialGovernanceTopology::class);

        $this->assertSame($root->id, $topology->parentOf($child)?->id);
        $this->assertSame([$child->id], $topology->childrenOf($root)->pluck('id')->all());
    }

    public function test_canonical_election_topology_source_does_not_use_legacy_geography_as_authority(): void
    {
        $path = app_path('Services/Elections/GovernanceElectionTopology.php');
        $this->assertFileExists($path);
        $source = file_get_contents($path);

        foreach (['location_level', 'address_id', 'cities', 'rurals', 'neighborhoods', 'streets', 'alleies'] as $legacyAuthority) {
            $this->assertStringNotContainsString($legacyAuthority, $source);
        }

        $this->assertStringContainsString('GovernanceArea', $source);
        $this->assertStringContainsString("area_kind', 'official'", $source);
        $this->assertStringContainsString("status', 'active'", $source);
    }

    public function test_election_cutover_flag_defaults_off(): void
    {
        $config = require config_path('location-governance.php');

        $this->assertArrayHasKey('elections_enabled', $config);
        $this->assertFalse($config['elections_enabled']);
    }
}
