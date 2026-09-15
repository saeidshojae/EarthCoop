<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\NajmBahar\Models\ProjectCategory;
use App\Modules\NajmBahar\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class ProjectScopeStoppingPointPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ProjectCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.projects_enabled' => true,
        ]);

        $this->user = User::factory()->create(['email_verified_at' => now()]);
        app(AccountService::class)->createMainAccountForUser($this->user->id);

        $this->category = ProjectCategory::create([
            'name' => 'Stopping point category',
            'level' => 1,
            'parent_id' => null,
            'status' => true,
        ]);
    }

    public function test_project_may_stop_on_continent_governance_without_a_target_location(): void
    {
        $asia = GovernanceArea::factory()->official()->create([
            'governance_type' => 'continent',
            'canonical_name' => 'Asia',
            'localized_names' => ['fa' => 'آسیا'],
            'status' => 'active',
        ]);

        $this->actingAs($this->user)
            ->post(route('najm-bahar.projects.store'), $this->validProjectPayload([
                'governance_area_id' => $asia->id,
                'target_location_id' => null,
            ]))
            ->assertSessionHas('success');

        $project = Project::query()->where('owner_id', $this->user->id)->latest('id')->firstOrFail();
        $this->assertSame($asia->id, $project->governance_area_id);
        $this->assertNull($project->target_location_id);
    }

    public function test_project_may_stop_on_intermediate_location_even_when_it_has_active_children(): void
    {
        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood',
        ]);

        $city = $path->firstWhere('level', 'city');
        $this->assertNotNull($city);
        $this->assertTrue($path->contains(fn ($location) => $location->parent_id === $city->id));

        $cityArea = GovernanceArea::factory()->official()->create([
            'governance_type' => 'city',
            'status' => 'active',
        ]);
        $cityArea->locations()->attach($city->id);

        $this->actingAs($this->user)
            ->post(route('najm-bahar.projects.store'), $this->validProjectPayload([
                'target_location_id' => $city->id,
            ]))
            ->assertSessionHas('success');

        $project = Project::query()->where('owner_id', $this->user->id)->latest('id')->firstOrFail();
        $this->assertSame($city->id, $project->target_location_id);
        $this->assertSame($cityArea->id, $project->governance_area_id);
    }

    public function test_edit_keeps_governance_only_scope_when_no_exact_location_was_selected(): void
    {
        $asia = GovernanceArea::factory()->official()->create([
            'governance_type' => 'continent',
            'status' => 'active',
        ]);

        $this->actingAs($this->user)
            ->post(route('najm-bahar.projects.store'), $this->validProjectPayload([
                'governance_area_id' => $asia->id,
            ]))
            ->assertSessionHas('success');

        $project = Project::query()->where('owner_id', $this->user->id)->latest('id')->firstOrFail();

        $this->actingAs($this->user)
            ->get(route('najm-bahar.projects.edit', $project))
            ->assertOk()
            ->assertSee('name="governance_area_id" value="'.$asia->id.'"', false)
            ->assertSee('name="target_location_id" value=""', false);
    }

    private function validProjectPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Stopping point project',
            'project_type' => 'production',
            'project_visibility' => 'public',
            'project_stage' => 'documented',
            'investment_method' => 'auction_shares',
            'category_level1_id' => $this->category->id,
            'problem_statement' => 'Problem statement',
            'solution_description' => 'Solution description',
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
            'summary' => 'Project summary',
            'description' => 'Project description',
        ], $overrides);
    }
}
