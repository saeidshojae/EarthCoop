<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\GovernanceArea;
use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\LocationGovernance\CommunityAreaService;
use App\Services\LocationGovernance\LocationProposalService;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\LocationGovernance\LocationFixture;
use Tests\TestCase;

class CommunityAreaUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
        ]);
    }

    public function test_eligible_approved_residence_exposes_policy_backed_community_create_action(): void
    {
        $this->assertTrue(Route::has('location-governance.community.store'));

        [$user, $complex] = $this->userAtComplexResidence();

        $response = $this->actingAs($user)->get(route('location-governance.me'));

        $response->assertOk();
        $response->assertSee('data-community-create-action', false);
        $response->assertSee(route('location-governance.community.store', $complex), false);
        $response->assertSee('اجتماع محلی', false);
        $response->assertSee('انتخابات رسمی', false);
    }

    public function test_existing_community_is_shown_without_duplicate_create_action_and_post_is_idempotent(): void
    {
        [$user, $complex] = $this->userAtComplexResidence();

        $route = route('location-governance.community.store', $complex);
        $this->actingAs($user)->post($route)->assertRedirect();
        $this->actingAs($user)->post($route)->assertRedirect();

        $community = GovernanceArea::query()
            ->where('area_kind', 'community')
            ->whereHas('locations', fn ($query) => $query->whereKey($complex->id))
            ->sole();

        $this->assertSame(1, GovernanceArea::query()
            ->where('area_kind', 'community')
            ->whereHas('locations', fn ($query) => $query->whereKey($complex->id))
            ->count());

        $response = $this->actingAs($user)->get(route('location-governance.me'));
        $response->assertOk();
        $response->assertSee('data-community-location="'.$complex->id.'"', false);
        $response->assertSee('اجتماع «'.$community->canonical_name.'» برای این مکان فعال است.');
        $response->assertDontSee(route('location-governance.community.store', $complex), false);
    }

    public function test_existing_community_auto_joins_another_resident_and_exposes_entry_action(): void
    {
        [$creator, $complex] = $this->userAtComplexResidence();
        $area = app(CommunityAreaService::class)->createFor($complex, $creator);
        $group = Group::query()
            ->where('governance_area_id', $area->id)
            ->where('dimension_key', 'public')
            ->sole();

        $resident = User::factory()->create();
        app(ResidenceService::class)->setInitialPrimaryResidence($resident, $complex, ['source' => 'test']);

        $response = $this->actingAs($resident)->get(route('location-governance.me'));

        $response->assertOk();
        $response->assertSee(route('groups.show', $group), false);
        $response->assertSee('data-community-enter-action', false);
        $membership = GroupUser::query()
            ->where('group_id', $group->id)
            ->where('user_id', $resident->id)
            ->sole();
        $this->assertSame(1, (int) $membership->role);
        $this->assertSame(1, (int) $membership->status);
    }

    public function test_outsider_cannot_create_or_join_an_unrelated_local_community(): void
    {
        [$creator, $complex] = $this->userAtComplexResidence();
        $area = app(CommunityAreaService::class)->createFor($complex, $creator);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('location-governance.community.store', $complex))
            ->assertSessionHasErrors('community');

        $this->assertNull(app(CommunityAreaService::class)->ensureMembership($area, $outsider));
        $group = app(CommunityAreaService::class)->publicAssemblyFor($area);
        $this->assertFalse(GroupUser::query()
            ->where('group_id', $group->id)
            ->where('user_id', $outsider->id)
            ->exists());
    }

    public function test_residence_transfer_deactivates_old_community_membership_without_page_visit(): void
    {
        [$user, $oldComplex] = $this->userAtComplexResidence();
        $oldArea = app(CommunityAreaService::class)->createFor($oldComplex, $user);
        $oldGroup = app(CommunityAreaService::class)->publicAssemblyFor($oldArea);

        $schema = $oldComplex->type->schemas()->where('location_schemas.status', 'active')->firstOrFail();
        $newPath = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood', 'street', 'complex',
        ], ['ایران دوم', 'استان دوم', 'شهرستان دوم', 'بخش دوم', 'شهر دوم', 'منطقه دوم', 'محله دوم', 'خیابان دوم', 'مجتمع دوم']);
        $newComplex = $newPath->last();

        app(ResidenceService::class)->transferPrimaryResidence(
            $user,
            $newComplex,
            $user,
            'community membership transfer regression',
            true,
        );

        $oldMembership = GroupUser::query()
            ->where('group_id', $oldGroup->id)
            ->where('user_id', $user->id)
            ->sole();
        $this->assertSame(0, (int) $oldMembership->status);
    }

    public function test_residence_change_joins_existing_community_without_dashboard_visit(): void
    {
        [$creator, $complex] = $this->userAtComplexResidence();
        $area = app(CommunityAreaService::class)->createFor($complex, $creator);
        $group = app(CommunityAreaService::class)->publicAssemblyFor($area);

        $resident = User::factory()->create();
        app(ResidenceService::class)->setInitialPrimaryResidence($resident, $complex, ['source' => 'test']);

        $membership = GroupUser::query()
            ->where('group_id', $group->id)
            ->where('user_id', $resident->id)
            ->sole();
        $this->assertSame(1, (int) $membership->role);
        $this->assertSame(1, (int) $membership->status);
    }

    public function test_pending_or_ineligible_residence_never_gets_community_create_action(): void
    {
        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood',
        ]);
        $neighborhood = $path->last();
        $streetType = $schema->types->firstWhere('key', 'street');
        $user = User::factory()->create();

        app(ResidenceService::class)->setInitialPrimaryResidence($user, $neighborhood, ['source' => 'test']);
        $proposal = app(LocationProposalService::class)->propose($user, $neighborhood, $streetType, [
            'canonical_name' => 'خیابان پیشنهادی در انتظار بررسی',
        ]);
        app(ResidenceService::class)->setPendingResidenceIntent($user, $proposal, ['source' => 'test']);

        $response = $this->actingAs($user)->get(route('location-governance.me'));

        $response->assertOk();
        $response->assertSee('data-pending-residence-intent', false);
        $response->assertDontSee('data-community-create-action', false);
    }

    private function userAtComplexResidence(): array
    {
        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath($schema, [
            'country', 'province', 'county', 'section', 'city', 'urban_region', 'neighborhood', 'street', 'complex',
        ]);
        $complex = $path->last();
        $user = User::factory()->create();

        app(ResidenceService::class)->setInitialPrimaryResidence($user, $complex, ['source' => 'test']);

        return [$user, $complex];
    }
}
