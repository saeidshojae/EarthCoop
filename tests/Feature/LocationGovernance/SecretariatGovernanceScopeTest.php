<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\Group;
use App\Modules\Secretariat\Services\SecretariatOfficeService;
use App\Services\LocationGovernance\GovernanceResourceScopeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecretariatGovernanceScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_secretariat_resolves_governance_area_from_canonical_group_scope(): void
    {
        $area = GovernanceArea::factory()->official()->create();
        $group = Group::create([
            'name' => 'Canonical Secretariat group',
            'group_type' => 0,
            'governance_area_id' => $area->id,
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
            'location_level' => 'legacy-level-must-not-be-authority',
        ]);

        $office = app(SecretariatOfficeService::class)->ensureGroup($group);
        $resolved = app(GovernanceResourceScopeResolver::class)->areaFor($office);

        $this->assertNotNull($resolved);
        $this->assertSame($area->id, $resolved->id);
    }
}
