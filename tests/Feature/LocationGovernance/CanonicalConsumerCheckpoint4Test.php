<?php

namespace Tests\Feature\LocationGovernance;

use App\Http\Controllers\Admin\GlobalGroupRoleController;
use App\Http\Controllers\Admin\UserController;
use App\Models\GovernanceArea;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\MembershipDimension;
use App\Models\User;
use App\Services\Elections\ElectionGroupDomainClassifier;
use App\Services\LocationGovernance\ResidenceService;
use App\Services\Membership\PublicDimensionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

final class CanonicalConsumerCheckpoint4Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
            'location-governance.groups_enabled' => true,
            'location-governance.projects_enabled' => true,
            'location-governance.elections_enabled' => true,
        ]);

        MembershipDimension::query()->firstOrCreate(
            ['key' => 'public'],
            [
                'name' => 'Public',
                'resolver_class' => PublicDimensionResolver::class,
                'enabled' => true,
            ],
        );
    }

    public function test_election_domain_level_prefers_canonical_governance_area_over_stale_legacy_level(): void
    {
        $area = GovernanceArea::factory()->official()->create([
            'governance_type' => 'city',
            'status' => 'active',
        ]);
        $group = Group::query()->create([
            'name' => 'Canonical city assembly',
            'group_type' => '0',
            'governance_area_id' => $area->id,
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
            'location_level' => 'province',
        ]);

        $this->assertSame('city', app(ElectionGroupDomainClassifier::class)->level($group));
    }

    public function test_global_role_preview_filters_canonical_groups_by_governance_area_level(): void
    {
        $area = GovernanceArea::factory()->official()->create([
            'governance_type' => 'city',
            'status' => 'active',
        ]);
        $group = Group::query()->create([
            'name' => 'Canonical city role target',
            'group_type' => '0',
            'governance_area_id' => $area->id,
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
            'location_level' => null,
        ]);
        $user = User::factory()->create();
        GroupUser::query()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 0,
            'status' => 1,
        ]);

        $request = Request::create('/admin/groups/global-roles/preview', 'POST', [
            'group_category' => 'general',
            'location_level' => 'city',
            'source_role' => 0,
            'target_role' => 5,
        ]);

        $response = app(GlobalGroupRoleController::class)->preview($request);
        $payload = $response->getData(true);

        $this->assertSame(1, $payload['groups']);
        $this->assertSame(1, $payload['memberships']);
        $this->assertSame(1, $payload['will_apply']);
    }

    public function test_admin_user_export_reads_canonical_primary_residence_when_legacy_address_is_absent(): void
    {
        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood'],
            ['ایران', 'مازندران', 'ساری', 'مرکزی', 'ساری', 'منطقه یک', 'محله مرجع'],
        );
        $user = User::factory()->create([
            'first_name' => 'کاربر',
            'last_name' => 'مکانی',
        ]);
        app(ResidenceService::class)->setInitialPrimaryResidence(
            $user,
            $path->last(),
            ['source' => 'checkpoint_4_export'],
        );

        $this->assertNull($user->fresh()->address);

        $response = app(UserController::class)->exportUsers([$user->id]);
        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();

        $this->assertStringContainsString('ایران', $csv);
        $this->assertStringContainsString('مازندران', $csv);
        $this->assertStringContainsString('ساری', $csv);
        $this->assertStringContainsString('مرکزی', $csv);
        $this->assertStringContainsString('منطقه یک', $csv);
        $this->assertStringContainsString('محله مرجع', $csv);
    }

    public function test_live_group_and_election_consumers_do_not_read_legacy_location_level_directly(): void
    {
        $systemicChat = file_get_contents(app_path('Http/Controllers/Group/SystemicElectionChatController.php'));
        $reports = file_get_contents(app_path('Http/Controllers/Group/ReportController.php'));
        $classifier = file_get_contents(app_path('Services/Elections/ElectionGroupDomainClassifier.php'));
        $appointment = file_get_contents(app_path('Listeners/AwardElectionAppointmentParticipation.php'));

        $this->assertStringNotContainsString("getRawOriginal('location_level')", $systemicChat);
        $this->assertStringNotContainsString('$group->location_level', $reports);
        $this->assertStringNotContainsString('$group->location_level', $classifier);
        $this->assertStringNotContainsString('$group->location_level', $appointment);
    }
}
