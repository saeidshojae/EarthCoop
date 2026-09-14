<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\User;
use App\Modules\NajmBahar\Models\ProjectCategory;
use App\Modules\NajmBahar\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectHierarchicalLocationPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_project_create_uses_shared_hierarchical_location_picker_instead_of_flat_governance_select(): void
    {
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.projects_enabled' => true,
        ]);

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        app(AccountService::class)->createMainAccountForUser($user->id);

        ProjectCategory::create([
            'name' => 'Project picker category',
            'level' => 1,
            'parent_id' => null,
            'status' => true,
        ]);

        $response = $this->actingAs($user)
            ->get(route('najm-bahar.projects.create'));

        $response->assertOk();
        $response->assertSee('data-location-selector', false);
        $response->assertSee('data-location-purpose="project-scope"', false);
        $response->assertSee('name="target_location_id"', false);
        $response->assertDontSee('data-governance-scope-cutover="canonical"', false);
        $response->assertDontSee('id="governance_area_select"', false);
    }
}
