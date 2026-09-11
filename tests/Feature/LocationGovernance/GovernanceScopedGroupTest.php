<?php

namespace Tests\Feature\LocationGovernance;

use App\Data\Membership\MembershipIntent;
use App\Models\GovernanceArea;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GovernanceScopedGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_group_scope_is_additive_and_keeps_legacy_columns(): void
    {
        $path = database_path('migrations/2026_09_10_000007_add_canonical_scope_to_groups.php');
        $this->assertFileExists($path);
        $source = file_get_contents($path);

        foreach (['governance_area_id', 'dimension_key', 'dimension_value_key'] as $column) {
            $this->assertStringContainsString($column, $source);
        }

        foreach (['address_id', 'location_level'] as $legacyColumn) {
            $this->assertStringNotContainsString("dropColumn('{$legacyColumn}')", $source);
            $this->assertStringNotContainsString('dropColumn("'.$legacyColumn.'")', $source);
        }
    }

    public function test_materialization_is_idempotent_for_same_canonical_identity(): void
    {
        $this->assertTrue(class_exists(\App\Services\Groups\GovernanceScopedGroupService::class));

        $area = GovernanceArea::factory()->create(['area_kind' => 'official', 'status' => 'active']);
        $intent = new MembershipIntent('public', 'public', $area->id, 'automatic', null, 'v1', null);
        $service = app(\App\Services\Groups\GovernanceScopedGroupService::class);

        $first = $service->materialize($intent);
        $count = Group::count();
        $second = $service->materialize($intent);

        $this->assertInstanceOf(Group::class, $first);
        $this->assertSame($first->id, $second?->id);
        $this->assertSame($count, Group::count());
        $this->assertSame($area->id, $first->governance_area_id);
        $this->assertSame('public', $first->dimension_key);
        $this->assertSame('public', $first->dimension_value_key);
    }

    public function test_same_dimension_value_in_different_governance_areas_creates_distinct_groups(): void
    {
        $service = app(\App\Services\Groups\GovernanceScopedGroupService::class);
        $firstArea = GovernanceArea::factory()->create(['area_kind' => 'official', 'status' => 'active']);
        $secondArea = GovernanceArea::factory()->create(['area_kind' => 'official', 'status' => 'active']);

        $first = $service->materialize(new MembershipIntent('public', 'public', $firstArea->id, 'automatic', null, 'v1', null));
        $second = $service->materialize(new MembershipIntent('public', 'public', $secondArea->id, 'automatic', null, 'v1', null));

        $this->assertNotSame($first?->id, $second?->id);
        $this->assertSame(2, Group::query()->where('dimension_key', 'public')->where('dimension_value_key', 'public')->count());
    }

    public function test_suppressed_intent_is_not_materialized(): void
    {
        $area = GovernanceArea::factory()->create(['area_kind' => 'official', 'status' => 'active']);
        $intent = new MembershipIntent('profession', 'occupational_field:1', $area->id, 'threshold', 20, 'v1', 'threshold');

        $before = Group::count();
        $group = app(\App\Services\Groups\GovernanceScopedGroupService::class)->materialize($intent);

        $this->assertNull($group);
        $this->assertSame($before, Group::count());
    }
}
