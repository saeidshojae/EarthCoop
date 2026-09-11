<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\Group;
use App\Models\Poll;
use App\Models\User;
use App\Services\LocationGovernance\GovernanceResourceScopeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PollGovernanceScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_poll_resolves_governance_scope_via_its_canonical_group(): void
    {
        $area = GovernanceArea::factory()->official()->create();
        $group = Group::create([
            'name' => 'Canonical poll group',
            'group_type' => 0,
            'governance_area_id' => $area->id,
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
            'location_level' => 'legacy-level-must-not-be-authority',
        ]);
        $creator = User::factory()->create();

        $poll = Poll::create([
            'group_id' => $group->id,
            'created_by' => $creator->id,
            'question' => 'Canonical scope?',
            'main_type' => 1,
        ]);

        $resolved = app(GovernanceResourceScopeResolver::class)->areaFor($poll);

        $this->assertNotNull($resolved);
        $this->assertSame($area->id, $resolved->id);
    }
}
