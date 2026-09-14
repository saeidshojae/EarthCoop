<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\User;
use App\Modules\NajmBahar\Models\Project;
use App\Modules\NajmBahar\Models\ProjectCategory;
use App\Modules\NajmBahar\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class ProjectHierarchicalLocationPickerTest extends TestCase
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

        $this->user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        app(AccountService::class)->createMainAccountForUser($this->user->id);

        $this->category = ProjectCategory::create([
            'name' => 'Project picker category',
            'level' => 1,
            'parent_id' => null,
            'status' => true,
        ]);
    }

    public function test_canonical_project_create_uses_shared_hierarchical_location_picker_instead_of_flat_governance_select(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('najm-bahar.projects.create'));

        $response->assertOk();
        $response->assertSee('data-location-selector', false);
        $response->assertSee('data-location-purpose="project-scope"', false);
        $response->assertSee('name="target_location_id"', false);
        $response->assertDontSee('data-governance-scope-cutover="canonical"', false);
        $response->assertDontSee('id="governance_area_select"', false);
    }

    public function test_project_schema_keeps_exact_target_location_separate_from_governance_scope(): void
    {
        $this->assertTrue(Schema::hasColumn('najm_bahar_projects', 'target_location_id'));
        $this->assertTrue(Schema::hasColumn('najm_bahar_projects', 'governance_area_id'));
    }

    public function test_store_persists_exact_location_and_derives_nearest_official_governance_scope_server_side(): void
    {
        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood', 'street',
        ]);

        $city = $path->firstWhere('level', 'city');
        $street = $path->last();
        $canonicalArea = GovernanceArea::factory()->official()->create([
            'status' => 'active',
            'rank' => 500,
        ]);
        $canonicalArea->locations()->attach($city->id);

        $spoofedArea = GovernanceArea::factory()->official()->create([
            'status' => 'active',
            'rank' => 900,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('najm-bahar.projects.store'), $this->validProjectPayload([
                'target_location_id' => $street->id,
                'governance_area_id' => $spoofedArea->id,
            ]));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('najm_bahar_projects', [
            'owner_type' => User::class,
            'owner_id' => $this->user->id,
            'target_location_id' => $street->id,
            'governance_area_id' => $canonicalArea->id,
        ]);
    }

    public function test_resolver_prefers_nearest_mapped_ancestor_over_more_remote_governance_area(): void
    {
        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood',
        ]);

        $country = $path->firstWhere('level', 'country');
        $city = $path->firstWhere('level', 'city');
        $neighborhood = $path->last();

        $countryArea = GovernanceArea::factory()->official()->create(['status' => 'active', 'rank' => 100]);
        $cityArea = GovernanceArea::factory()->official()->create(['status' => 'active', 'rank' => 500]);
        $countryArea->locations()->attach($country->id);
        $cityArea->locations()->attach($city->id);

        $this->actingAs($this->user)
            ->post(route('najm-bahar.projects.store'), $this->validProjectPayload([
                'target_location_id' => $neighborhood->id,
            ]))
            ->assertSessionHas('success');

        $project = Project::query()->where('owner_id', $this->user->id)->latest('id')->firstOrFail();
        $this->assertSame($neighborhood->id, $project->target_location_id);
        $this->assertSame($cityArea->id, $project->governance_area_id);
    }

    public function test_store_rejects_active_location_that_has_no_official_governance_mapping_in_its_ancestry(): void
    {
        $schema = LocationFixture::iranSchema();
        $target = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last();

        $response = $this->actingAs($this->user)
            ->from(route('najm-bahar.projects.create'))
            ->post(route('najm-bahar.projects.store'), $this->validProjectPayload([
                'target_location_id' => $target->id,
            ]));

        $response->assertRedirect(route('najm-bahar.projects.create'));
        $response->assertSessionHasErrors('target_location_id');
        $this->assertDatabaseMissing('najm_bahar_projects', [
            'owner_type' => User::class,
            'owner_id' => $this->user->id,
        ]);
    }

    public function test_edit_preserves_exact_target_location_and_update_rederives_governance_scope(): void
    {
        $schema = LocationFixture::iranSchema();
        $firstPath = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood',
        ]);
        $secondPath = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood',
        ]);

        $firstCity = $firstPath->firstWhere('level', 'city');
        $firstTarget = $firstPath->last();
        $secondCity = $secondPath->firstWhere('level', 'city');
        $secondTarget = $secondPath->last();

        $firstArea = GovernanceArea::factory()->official()->create(['status' => 'active']);
        $secondArea = GovernanceArea::factory()->official()->create(['status' => 'active']);
        $firstArea->locations()->attach($firstCity->id);
        $secondArea->locations()->attach($secondCity->id);

        $this->actingAs($this->user)
            ->post(route('najm-bahar.projects.store'), $this->validProjectPayload([
                'target_location_id' => $firstTarget->id,
            ]))
            ->assertSessionHas('success');

        $project = Project::query()->where('owner_id', $this->user->id)->latest('id')->firstOrFail();

        $edit = $this->actingAs($this->user)
            ->get(route('najm-bahar.projects.edit', $project));
        $edit->assertOk();
        $edit->assertSee('name="target_location_id" value="'.$firstTarget->id.'"', false);

        $this->actingAs($this->user)
            ->put(route('najm-bahar.projects.update', $project), $this->validProjectPayload([
                'target_location_id' => $secondTarget->id,
            ]))
            ->assertSessionHas('success');

        $project->refresh();
        $this->assertSame($secondTarget->id, $project->target_location_id);
        $this->assertSame($secondArea->id, $project->governance_area_id);
    }

    public function test_inactive_target_location_is_rejected_before_project_is_written(): void
    {
        $schema = LocationFixture::iranSchema();
        $target = LocationFixture::createPath($schema, ['country', 'province', 'county', 'section', 'city'])->last();
        $target->update(['status' => 'inactive']);

        $response = $this->actingAs($this->user)
            ->from(route('najm-bahar.projects.create'))
            ->post(route('najm-bahar.projects.store'), $this->validProjectPayload([
                'target_location_id' => $target->id,
            ]));

        $response->assertRedirect(route('najm-bahar.projects.create'));
        $response->assertSessionHasErrors('target_location_id');
        $this->assertDatabaseMissing('najm_bahar_projects', [
            'owner_type' => User::class,
            'owner_id' => $this->user->id,
        ]);
    }

    private function validProjectPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Hierarchical location project',
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
