<?php

namespace Tests\Feature\LocationGovernance;

use App\Http\Controllers\Admin\GlobalGroupRoleController;
use App\Http\Controllers\Admin\GroupController;
use App\Models\GovernanceArea;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\Elections\ElectionGroupDomainClassifier;
use App\Services\Elections\ElectionPolicyResolver;
use App\Services\Groups\GroupMembershipRoleResolver;
use App\Services\LocationGovernance\GroupGovernanceContext;
use App\Services\NajmHoda\Context\NajmHodaPageContextResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Tests\TestCase;

final class CanonicalConsumerAlignmentCheckpoint4Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
            'location-governance.groups_enabled' => true,
            'location-governance.elections_enabled' => true,
        ]);
    }

    public function test_live_routes_resolve_to_canonical_consumers(): void
    {
        $this->assertSame(
            \App\Http\Controllers\Profile\RuntimeProfileController::class.'@showProfile',
            Route::getRoutes()->getByName('profile.show')?->getActionName(),
        );
        $this->assertSame(
            \App\Http\Controllers\LocationGovernance\CanonicalGroupIndexController::class,
            Route::getRoutes()->getByName('groups.index')?->getActionName(),
        );
        $this->assertSame(
            \App\Http\Controllers\Group\SystemicElectionChatController::class.'@chat',
            Route::getRoutes()->getByName('groups.chat')?->getActionName(),
        );
        $this->assertSame(
            \App\Http\Controllers\LocationGovernance\MyLocationGovernanceController::class,
            Route::getRoutes()->getByName('location-governance.me')?->getActionName(),
        );
    }

    public function test_canonical_membership_pivot_role_is_authoritative_while_legacy_fallback_is_preserved(): void
    {
        $area = GovernanceArea::factory()->official()->create([
            'governance_type' => 'local',
            'status' => 'active',
        ]);
        $canonical = Group::create([
            'name' => 'Canonical local',
            'group_type' => 0,
            'governance_area_id' => $area->id,
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
        ]);
        $user = User::factory()->create();
        $membership = GroupUser::create([
            'group_id' => $canonical->id,
            'user_id' => $user->id,
            'role' => 1,
            'status' => 1,
        ]);

        $resolver = app(GroupMembershipRoleResolver::class);
        $this->assertSame(1, $resolver->effectiveRole($canonical, $membership));

        $membership->forceFill(['role' => 0])->save();
        $this->assertSame(0, $resolver->effectiveRole($canonical, $membership->fresh()));

        config(['location-governance.groups_enabled' => false]);
        $legacy = Group::create([
            'name' => 'Legacy neighborhood',
            'group_type' => 0,
            'location_level' => 'neighborhood',
        ]);
        $legacyMembership = GroupUser::create([
            'group_id' => $legacy->id,
            'user_id' => $user->id,
            'role' => 0,
            'status' => 1,
        ]);

        $this->assertSame(1, $resolver->effectiveRole($legacy, $legacyMembership));
    }

    public function test_canonical_election_consumers_normalize_governance_types_without_using_location_level(): void
    {
        $cases = [
            ['local', 'neighborhood', 'neighborhood'],
            ['urban_region', 'region', 'region'],
            ['rural_district', 'rural', 'city'],
            ['village', 'village', 'region'],
            ['section', 'section', 'district'],
        ];

        $context = app(GroupGovernanceContext::class);
        $classifier = app(ElectionGroupDomainClassifier::class);
        $policy = app(ElectionPolicyResolver::class);

        foreach ($cases as [$governanceType, $conflictLevel, $policyLevel]) {
            $area = GovernanceArea::factory()->official()->create([
                'governance_type' => $governanceType,
                'status' => 'active',
            ]);
            $group = Group::create([
                'name' => 'Canonical '.$governanceType,
                'group_type' => 0,
                'location_level' => 'global',
                'governance_area_id' => $area->id,
                'dimension_key' => 'public',
                'dimension_value_key' => 'public',
            ]);

            $this->assertSame($conflictLevel, $context->officialElectionLevel($group));
            $this->assertSame($conflictLevel, $classifier->level($group));
            $this->assertSame($policyLevel, $policy->levelKeyForGroup($group));
        }

        $area = GovernanceArea::factory()->official()->create([
            'governance_type' => 'city',
            'status' => 'active',
        ]);
        $professional = Group::create([
            'name' => 'Canonical profession',
            'group_type' => 0,
            'location_level' => 'global',
            'governance_area_id' => $area->id,
            'dimension_key' => 'profession',
            'dimension_value_key' => 'occupational_field:12',
        ]);

        $this->assertSame('job', $classifier->domain($professional));
        $this->assertSame('city_job', $policy->levelKeyForGroup($professional));
    }

    public function test_admin_group_filters_use_canonical_governance_and_dimensions(): void
    {
        $regionArea = GovernanceArea::factory()->official()->create([
            'governance_type' => 'urban_region',
            'status' => 'active',
        ]);
        $cityArea = GovernanceArea::factory()->official()->create([
            'governance_type' => 'city',
            'status' => 'active',
        ]);

        $wanted = Group::create([
            'name' => 'Wanted canonical profession',
            'group_type' => 0,
            'governance_area_id' => $regionArea->id,
            'dimension_key' => 'profession',
            'dimension_value_key' => 'occupational_field:1',
        ]);
        Group::create([
            'name' => 'Wrong level profession',
            'group_type' => 0,
            'governance_area_id' => $cityArea->id,
            'dimension_key' => 'profession',
            'dimension_value_key' => 'occupational_field:1',
        ]);
        Group::create([
            'name' => 'Wrong dimension',
            'group_type' => 0,
            'governance_area_id' => $regionArea->id,
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
        ]);

        $view = app(GroupController::class)->index(Request::create('/admin/groups', 'GET', [
            'level' => 'region',
            'sort' => 'job',
        ]));

        $groups = collect($view->getData()['groups']);
        $this->assertSame([$wanted->id], $groups->pluck('id')->all());
    }

    public function test_bulk_role_filter_uses_canonical_scope_instead_of_legacy_group_fields(): void
    {
        $regionArea = GovernanceArea::factory()->official()->create([
            'governance_type' => 'urban_region',
            'status' => 'active',
        ]);
        $cityArea = GovernanceArea::factory()->official()->create([
            'governance_type' => 'city',
            'status' => 'active',
        ]);
        $user = User::factory()->create();

        $wanted = Group::create([
            'name' => 'Canonical specialized region',
            'group_type' => 0,
            'location_level' => 'global',
            'governance_area_id' => $regionArea->id,
            'dimension_key' => 'specialty',
            'dimension_value_key' => 'experience_field:4',
        ]);
        $wrong = Group::create([
            'name' => 'Canonical public city',
            'group_type' => 1,
            'location_level' => 'region',
            'governance_area_id' => $cityArea->id,
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
        ]);

        GroupUser::create(['group_id' => $wanted->id, 'user_id' => $user->id, 'role' => 0, 'status' => 1]);
        GroupUser::create(['group_id' => $wrong->id, 'user_id' => $user->id, 'role' => 0, 'status' => 1]);

        $method = new ReflectionMethod(GlobalGroupRoleController::class, 'candidateQuery');
        $method->setAccessible(true);
        $query = $method->invoke(app(GlobalGroupRoleController::class), [
            'group_category' => 'specialized',
            'location_level' => 'region',
            'source_role' => 0,
            'target_role' => 5,
        ]);

        $this->assertSame([$wanted->id], $query->pluck('group_user.group_id')->all());
    }

    public function test_najm_hoda_group_context_exposes_canonical_scope_identity(): void
    {
        $area = GovernanceArea::factory()->official()->create([
            'governance_type' => 'city',
            'status' => 'active',
        ]);
        $group = Group::create([
            'name' => 'Canonical context group',
            'group_type' => 0,
            'governance_area_id' => $area->id,
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
            'is_open' => true,
        ]);
        $user = User::factory()->create();
        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => 0,
            'status' => 1,
        ]);

        $resolved = app(NajmHodaPageContextResolver::class)->resolve($user, [
            'page' => [
                'route_name' => 'groups.chat',
                'module' => 'groups',
                'resource_type' => 'group',
                'resource_id' => $group->id,
            ],
        ]);

        $resource = $resolved['resource'];
        $this->assertSame($area->id, $resource['governance_area_id']);
        $this->assertSame('public', $resource['dimension_key']);
        $this->assertSame('public', $resource['dimension_value_key']);
        $this->assertStringContainsString('governance:'.$area->id, $resource['scope_key']);
    }
}
