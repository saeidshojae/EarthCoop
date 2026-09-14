<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\NajmBahar\Models\ProjectCategory;
use App\Modules\NajmBahar\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectScopeCutoverFlagTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ProjectCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        app(AccountService::class)->createMainAccountForUser($this->user->id);

        $this->category = ProjectCategory::create([
            'name' => 'Canonical scope category',
            'level' => 1,
            'parent_id' => null,
            'status' => true,
        ]);
    }

    public function test_canonical_project_form_replaces_legacy_geography_selector_with_shared_location_picker_when_flag_is_enabled(): void
    {
        config(['location-governance.projects_enabled' => true]);

        $response = $this->actingAs($this->user)
            ->get(route('najm-bahar.projects.create'));

        $response->assertOk();
        $response->assertSee('data-location-selector', false);
        $response->assertSee('data-location-purpose="project-scope"', false);
        $response->assertSee('name="target_location_id"', false);
        $response->assertDontSee('id="governance_area_select"', false);
        $response->assertDontSee('name="geographic_continent_id"', false);
    }

    public function test_canonical_project_store_still_accepts_valid_explicit_governance_scope_as_backward_compatible_fallback(): void
    {
        config(['location-governance.projects_enabled' => true]);

        $area = GovernanceArea::factory()->official()->create();

        $response = $this->actingAs($this->user)
            ->post(route('najm-bahar.projects.store'), $this->validProjectPayload([
                'governance_area_id' => $area->id,
                'geographic_city_id' => 987654,
            ]));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('najm_bahar_projects', [
            'title' => 'Canonical controller project',
            'owner_type' => User::class,
            'owner_id' => $this->user->id,
            'governance_area_id' => $area->id,
            'geographic_city_id' => null,
        ]);
    }

    public function test_canonical_project_store_rejects_inactive_governance_scope(): void
    {
        config(['location-governance.projects_enabled' => true]);

        $inactiveArea = GovernanceArea::factory()->official()->create([
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($this->user)
            ->from(route('najm-bahar.projects.create'))
            ->post(route('najm-bahar.projects.store'), $this->validProjectPayload([
                'governance_area_id' => $inactiveArea->id,
            ]));

        $response->assertRedirect(route('najm-bahar.projects.create'));
        $response->assertSessionHasErrors('governance_area_id');
        $this->assertDatabaseMissing('najm_bahar_projects', [
            'title' => 'Canonical controller project',
        ]);
    }

    public function test_canonical_project_store_rejects_non_official_governance_scope(): void
    {
        config(['location-governance.projects_enabled' => true]);

        $communityArea = GovernanceArea::factory()->create([
            'area_kind' => 'community',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)
            ->from(route('najm-bahar.projects.create'))
            ->post(route('najm-bahar.projects.store'), $this->validProjectPayload([
                'governance_area_id' => $communityArea->id,
            ]));

        $response->assertRedirect(route('najm-bahar.projects.create'));
        $response->assertSessionHasErrors('governance_area_id');
        $this->assertDatabaseMissing('najm_bahar_projects', [
            'title' => 'Canonical controller project',
        ]);
    }

    public function test_canonical_project_update_rejects_inactive_governance_scope(): void
    {
        config(['location-governance.projects_enabled' => true]);

        $activeArea = GovernanceArea::factory()->official()->create();
        $inactiveArea = GovernanceArea::factory()->official()->create([
            'status' => 'inactive',
        ]);

        $this->actingAs($this->user)
            ->post(route('najm-bahar.projects.store'), $this->validProjectPayload([
                'governance_area_id' => $activeArea->id,
            ]))
            ->assertSessionHas('success');

        $project = Project::query()
            ->where('owner_type', User::class)
            ->where('owner_id', $this->user->id)
            ->firstOrFail();

        $response = $this->actingAs($this->user)
            ->from(route('najm-bahar.projects.edit', $project))
            ->put(route('najm-bahar.projects.update', $project), $this->validProjectPayload([
                'governance_area_id' => $inactiveArea->id,
            ]));

        $response->assertRedirect(route('najm-bahar.projects.edit', $project));
        $response->assertSessionHasErrors('governance_area_id');
        $this->assertSame($activeArea->id, $project->fresh()->governance_area_id);
    }

    public function test_legacy_project_form_remains_available_when_flag_is_disabled(): void
    {
        config(['location-governance.projects_enabled' => false]);

        $response = $this->actingAs($this->user)
            ->get(route('najm-bahar.projects.create'));

        $response->assertOk();
        $response->assertSee('name="geographic_continent_id"', false);
        $response->assertDontSee('name="target_location_id"', false);
        $response->assertDontSee('data-location-purpose="project-scope"', false);
    }

    private function validProjectPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Canonical controller project',
            'project_type' => 'production',
            'project_visibility' => 'public',
            'project_stage' => 'documented',
            'investment_method' => 'auction_shares',
            'category_level1_id' => $this->category->id,
            'problem_statement' => 'Canonical problem statement',
            'solution_description' => 'Canonical solution description',
            'target_market' => 'general',
            'base_value_min' => 1000000,
            'base_value_max' => 5000000,
            'total_shares' => 100,
            'initial_auction_percent' => 30,
            'max_user_ownership_percent' => 20,
            'auction_period' => 'monthly',
            'risk_level' => 'medium',
            'oversight_type' => 'guild',
            'reporting_interval' => 'monthly',
            'fund_usage_scope' => 'project_only',
            'accept_transparency' => '1',
            'failure_policy' => 'refund',
            'value_update_trigger' => 'stage_progress',
            'accept_rules' => '1',
            'summary' => 'Canonical project summary',
            'description' => 'Canonical project description',
        ], $overrides);
    }
}
