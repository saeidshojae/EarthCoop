<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\Election;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\MembershipFixture;
use Tests\TestCase;

class StageDLegacyElectionPortalBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_election_portal_rejects_active_legacy_systemic_group_in_canonical_mode(): void
    {
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
            'location-governance.groups_enabled' => true,
            'location-governance.elections_enabled' => true,
        ]);

        ['user' => $user] = MembershipFixture::canonicalUser();
        $this->actingAs($user)->get('/groups')->assertOk();

        $legacyGroup = Group::create([
            'name' => 'مجمع عمومی محله سوهانک',
            'group_type' => '0',
            'location_level' => 'neighborhood',
            'governance_area_id' => null,
            'dimension_key' => null,
            'dimension_value_key' => null,
        ]);

        $user->groups()->syncWithoutDetaching([
            $legacyGroup->id => ['role' => 1, 'status' => 1],
        ]);

        $legacyElection = Election::create([
            'group_id' => $legacyGroup->id,
            'governance_area_id' => null,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'is_closed' => false,
            'lifecycle_status' => 'open',
            'cycle_number' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('elections.portal', [
                'group' => $legacyGroup,
                'election_id' => $legacyElection->id,
            ]))
            ->assertForbidden();
    }
}
