<?php

namespace Tests\Feature\Groups;

use App\Models\GovernanceArea;
use App\Models\Group;
use App\Services\Groups\CurrentGroupAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\MembershipFixture;
use Tests\TestCase;

final class CurrentGroupAccessServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_active_canonical_and_legacy_memberships_are_not_authorized(): void
    {
        config(['location-governance.groups_enabled' => true]);
        ['user' => $user] = MembershipFixture::canonicalUser();
        $priorArea = GovernanceArea::create([
            'key' => 'previous-location',
            'country_code' => 'IR',
            'governance_type' => 'city',
            'area_kind' => 'official',
            'canonical_name' => 'Previous',
            'rank' => 10,
            'status' => 'active',
        ]);
        $oldCanonical = Group::create([
            'name' => 'Previous canonical public',
            'group_type' => '0',
            'governance_area_id' => $priorArea->id,
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
            'is_open' => 1,
        ]);
        $oldLegacy = Group::create([
            'name' => 'Previous legacy public',
            'group_type' => '0',
            'location_level' => 'city',
            'is_open' => 1,
        ]);
        $managed = Group::create([
            'name' => 'Managed',
            'group_type' => '0',
            'location_level' => 10,
            'is_open' => 1,
        ]);
        foreach ([$oldCanonical, $oldLegacy, $managed] as $group) {
            $user->groups()->attach($group->id, ['status' => 1, 'role' => 0]);
        }
        $access = app(CurrentGroupAccessService::class);

        $this->assertFalse($access->contains($user, $oldCanonical->id));
        $this->assertFalse($access->contains($user, $oldLegacy->id));
        $this->assertTrue($access->contains($user, $managed->id));
    }
}
