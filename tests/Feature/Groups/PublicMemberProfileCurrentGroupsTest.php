<?php

namespace Tests\Feature\Groups;

use App\Http\Controllers\Profile\ProfileController;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicMemberProfileCurrentGroupsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_profile_hides_active_legacy_system_groups_and_inactive_canonical_groups(): void
    {
        config(['location-governance.groups_enabled' => true]);
        $viewer = User::factory()->create();
        $member = User::factory()->create();
        $legacy = Group::query()->create(['name' => 'Historic city group', 'group_type' => '0', 'location_level' => 'city', 'is_open' => 1]);
        $inactive = Group::query()->create(['name' => 'Inactive legacy managed', 'group_type' => '0', 'location_level' => 10, 'is_open' => 1]);
        $managed = Group::query()->create(['name' => 'Current managed', 'group_type' => '0', 'location_level' => 10, 'is_open' => 1]);

        $member->groups()->attach($legacy->id, ['role' => 0, 'status' => 1]);
        $member->groups()->attach($inactive->id, ['role' => 0, 'status' => 0]);
        $member->groups()->attach($managed->id, ['role' => 1, 'status' => 1]);

        $this->actingAs($viewer);
        $response = app(ProfileController::class)->showProfileMember($member);
        $this->assertSame('profile.profile-member', $response->name());
        $visible = $response->getData()['generalGroups']->pluck('id')->all();
        $this->assertContains($managed->id, $visible);
        $this->assertNotContains($legacy->id, $visible);
        $this->assertNotContains($inactive->id, $visible);
    }

    public function test_public_profile_does_not_expose_stale_active_canonical_membership(): void
    {
        config(['location-governance.groups_enabled' => true]);
        ['user' => $member] = \Tests\Support\LocationGovernance\MembershipFixture::canonicalUser();
        $viewer = User::factory()->create();
        $priorArea = \App\Models\GovernanceArea::query()->create([
            'key' => 'prior-profile-area',
            'country_code' => 'IR',
            'governance_type' => 'city',
            'area_kind' => 'official',
            'canonical_name' => 'Previous city',
            'rank' => 10,
            'status' => 'active',
        ]);
        $oldGroup = Group::query()->create([
            'name' => 'Prior canonical public',
            'group_type' => '0',
            'governance_area_id' => $priorArea->id,
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
            'is_open' => 1,
        ]);
        $member->groups()->attach($oldGroup->id, ['role' => 0, 'status' => 1]);

        $this->actingAs($viewer);
        $view = app(ProfileController::class)->showProfileMember($member);
        $this->assertNotContains($oldGroup->id, $view->getData()['generalGroups']->pluck('id')->all());
    }

    public function test_public_profile_displays_nine_pending_groups_without_materializing_membership(): void
    {
        config(['location-governance.groups_enabled' => true]);
        $viewer = User::factory()->create();
        $member = User::factory()->create(['show_groups' => true]);
        foreach (['public' => 1, 'profession' => 3, 'specialty' => 3, 'age' => 1, 'gender' => 1] as $dimension => $count) {
            for ($i = 1; $i <= $count; $i++) {
                \App\Models\LocationScopedGroupRequest::query()->create([
                    'requester_user_id' => $member->id,
                    'scope_kind' => 'official_system',
                    'dimension_key' => $dimension,
                    'dimension_value_key' => $dimension.'-'.$i,
                    'status' => 'pending_location',
                    'metadata' => ['type_key' => 'neighborhood', 'is_pending_base' => true],
                ]);
            }
        }

        $beforePivots = \App\Models\GroupUser::query()->count();
        $this->actingAs($viewer);
        $view = app(ProfileController::class)->showProfileMember($member);
        $data = $view->getData();

        $this->assertSame(1, $data['generalGroups']->count());
        $this->assertSame(3, $data['specialityGroups']->count());
        $this->assertSame(3, $data['experienceGroups']->count());
        $this->assertSame(1, $data['ageGroups']->count());
        $this->assertSame(1, $data['genderGroups']->count());
        $this->assertSame($beforePivots, \App\Models\GroupUser::query()->count());
        $this->assertTrue($data['generalGroups']->first()->pending_location);
    }

    public function test_hidden_groups_privacy_suppresses_canonical_and_pending_data_from_public_view(): void
    {
        config(['location-governance.groups_enabled' => true]);
        $viewer = User::factory()->create();
        $member = User::factory()->create(['show_groups' => false]);
        $legacy = Group::query()->create([
            'name' => 'Hidden group',
            'group_type' => '0',
            'location_level' => 10,
            'is_open' => 1,
        ]);
        $member->groups()->attach($legacy->id, ['role' => 1, 'status' => 1]);
        \App\Models\LocationScopedGroupRequest::query()->create([
            'requester_user_id' => $member->id,
            'scope_kind' => 'official_system',
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
            'status' => 'pending_location',
            'metadata' => ['type_key' => 'neighborhood'],
        ]);

        $this->actingAs($viewer);
        $data = app(ProfileController::class)->showProfileMember($member)->getData();
        foreach (['generalGroups', 'specialityGroups', 'experienceGroups', 'ageGroups', 'genderGroups'] as $dimension) {
            $this->assertCount(0, $data[$dimension]);
        }
    }
}
