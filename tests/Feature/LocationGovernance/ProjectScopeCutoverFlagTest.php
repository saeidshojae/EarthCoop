<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\User;
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

    public function test_canonical_project_form_replaces_legacy_geography_selector_when_flag_is_enabled(): void
    {
        config(['location-governance.projects_enabled' => true]);

        $area = GovernanceArea::factory()->official()->create([
            'canonical_name' => 'Sari canonical area',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('najm-bahar.projects.create'));

        $response->assertOk();
        $response->assertSee('name="governance_area_id"', false);
        $response->assertSee((string) $area->canonical_name);
        $response->assertDontSee('name="geographic_continent_id"', false);
    }

    public function test_canonical_project_store_persists_governance_scope_when_flag_is_enabled(): void
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
        ]);
    }

    public function test_legacy_project_form_remains_available_when_flag_is_disabled(): void
    {
        config(['location-governance.projects_enabled' => false]);

        $response = $this->actingAs($this->user)
            ->get(route('najm-bahar.projects.create'));

        $response->assertOk();
        $response->assertSee('name="geographic_continent_id"', false);
        $response->assertDontSee('name="governance_area_id"', false);
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
