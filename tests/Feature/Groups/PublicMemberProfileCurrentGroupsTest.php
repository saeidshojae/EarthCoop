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
}
