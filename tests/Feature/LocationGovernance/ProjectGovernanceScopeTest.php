<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\Group;
use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\NajmBahar\Models\ProjectCategory;
use App\Modules\NajmBahar\Services\ProjectService;
use App\Services\LocationGovernance\GovernanceResourceScopeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProjectGovernanceScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_can_persist_and_resolve_canonical_governance_scope(): void
    {
        $this->assertTrue(Schema::hasColumn('najm_bahar_projects', 'governance_area_id'));

        $area = GovernanceArea::factory()->official()->create();
        $owner = Group::create([
            'name' => 'Canonical project owner',
            'group_type' => 0,
            'governance_area_id' => $area->id,
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
        ]);

        $project = Project::create([
            'owner_type' => Group::class,
            'owner_id' => $owner->id,
            'governance_area_id' => $area->id,
            'title' => 'Canonical scoped project',
            'summary' => 'Project scope contract',
        ]);

        $resolved = app(GovernanceResourceScopeResolver::class)->areaFor($project);

        $this->assertSame($area->id, $project->fresh()->governance_area_id);
        $this->assertNotNull($resolved);
        $this->assertSame($area->id, $resolved->id);
    }

    public function test_project_service_snapshots_canonical_scope_from_group_owner(): void
    {
        $area = GovernanceArea::factory()->official()->create();
        $owner = Group::create([
            'name' => 'Canonical project group',
            'group_type' => 0,
            'governance_area_id' => $area->id,
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
        ]);
        $category = ProjectCategory::create([
            'name' => 'Canonical scope category',
            'level' => 1,
            'status' => true,
        ]);

        $project = app(ProjectService::class)->createProject($owner, [
            'title' => 'Group-owned canonical project',
            'category_level1_id' => $category->id,
            'summary' => 'Canonical project service contract',
        ]);

        $this->assertSame($area->id, $project->governance_area_id);
    }

    public function test_project_service_persists_explicit_canonical_scope_for_user_owner(): void
    {
        $area = GovernanceArea::factory()->official()->create();
        $owner = User::factory()->create();
        $category = ProjectCategory::create([
            'name' => 'Explicit scope category',
            'level' => 1,
            'status' => true,
        ]);

        $project = app(ProjectService::class)->createProject($owner, [
            'title' => 'User-owned canonical project',
            'category_level1_id' => $category->id,
            'governance_area_id' => $area->id,
            'summary' => 'Explicit governance scope contract',
        ]);

        $this->assertSame($area->id, $project->governance_area_id);
    }

    public function test_legacy_project_geography_columns_remain_available_for_rollback(): void
    {
        $this->assertTrue(Schema::hasColumn('najm_bahar_projects', 'geographic_country_id'));
        $this->assertTrue(Schema::hasColumn('najm_bahar_projects', 'geographic_city_id'));
    }
}
