<?php

namespace Tests\Architecture;

use App\Models\GovernanceArea;
use App\Models\Group;
use App\Services\Elections\ElectionGroupDomainClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NoLegacyGeographyRuntimeDependencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_group_and_election_consumers_do_not_read_legacy_group_spatial_authority_directly(): void
    {
        $files = [
            'app/Http/Controllers/Group/SystemicElectionChatController.php',
            'app/Http/Controllers/Group/ReportController.php',
            'app/Http/Controllers/Admin/GroupController.php',
            'app/Http/Controllers/Admin/GlobalGroupRoleController.php',
            'app/Services/Elections/ElectionGroupDomainClassifier.php',
            'app/Listeners/AwardElectionAppointmentParticipation.php',
            'app/Observers/NajmHoda/FounderOperationalDomainObserver.php',
            'resources/views/partials/group-table.blade.php',
            'resources/views/groups/partials/group_hero.blade.php',
        ];

        foreach ($files as $file) {
            $source = file_get_contents(base_path($file));

            $this->assertStringNotContainsString('->location_level', $source, $file.' still reads groups.location_level directly.');
            $this->assertStringNotContainsString('groups.location_level', $source, $file.' still filters by groups.location_level.');
            $this->assertStringNotContainsString('->address_id', $source, $file.' still reads groups.address_id directly.');
        }
    }

    public function test_canonical_group_api_does_not_publish_legacy_location_level_as_scope_authority(): void
    {
        $source = file_get_contents(base_path('routes/web.php'));

        $this->assertStringNotContainsString("'location_level' => \$group->location_level", $source);
    }

    public function test_election_domain_level_comes_from_active_official_governance_area_when_canonical_elections_are_enabled(): void
    {
        config(['location-governance.elections_enabled' => true]);

        $area = GovernanceArea::factory()->official()->create([
            'governance_type' => 'city',
            'status' => 'active',
        ]);
        $group = Group::query()->create([
            'name' => 'Canonical city assembly',
            'group_type' => 0,
            'governance_area_id' => $area->id,
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
            'location_level' => null,
            'address_id' => null,
        ]);

        $this->assertSame('city', app(ElectionGroupDomainClassifier::class)->level($group));
    }

    public function test_profile_runtime_shadow_and_canonical_project_scope_remain_the_authoritative_consumers(): void
    {
        $routes = file_get_contents(base_path('routes/profile-canonical-runtime.php'));
        $runtimeProfile = file_get_contents(app_path('Http/Controllers/Profile/RuntimeProfileController.php'));
        $project = file_get_contents(app_path('Modules/NajmBahar/Services/ProjectService.php'));

        $this->assertStringContainsString('RuntimeProfileController', $routes);
        $this->assertStringContainsString('ProfileCompletionService::class', $runtimeProfile);
        $this->assertStringNotContainsString('Address::', $runtimeProfile);
        $this->assertStringContainsString("'governance_area_id'", $project);
        $this->assertStringNotContainsString("geographic_city_id' =>", $project);
    }
}
