<?php

namespace Tests\Feature\LocationGovernance;

use App\Models\Election;
use App\Models\GovernanceArea;
use App\Models\Group;
use App\Models\Location;
use App\Models\Poll;
use App\Services\Elections\CurrentElectionCenterService;
use App\Services\LocationGovernance\ResidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LocationGovernance\MembershipFixture;
use Tests\TestCase;

class StageCDProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_groups_public_chain_is_presented_from_most_local_to_global(): void
    {
        $this->enableStageCD();

        ['user' => $user, 'area' => $baseArea] = MembershipFixture::canonicalUser();

        $chain = [
            ['type' => 'global', 'rank' => 0, 'name' => 'Global'],
            ['type' => 'continent', 'rank' => 50, 'name' => 'Asia'],
            ['type' => 'country', 'rank' => 100, 'name' => 'Iran'],
            ['type' => 'province', 'rank' => 200, 'name' => 'Mazandaran'],
            ['type' => 'county', 'rank' => 300, 'name' => 'Sari County'],
            ['type' => 'section', 'rank' => 400, 'name' => 'Central Section'],
            ['type' => 'city', 'rank' => 500, 'name' => 'Sari'],
            ['type' => 'urban_region', 'rank' => 700, 'name' => 'Sari Region 1'],
        ];

        $parent = null;
        foreach ($chain as $index => $node) {
            $area = GovernanceArea::create([
                'key' => 'stage-cd-order-'.$index,
                'country_code' => in_array($node['type'], ['global', 'continent'], true) ? null : 'IR',
                'governance_type' => $node['type'],
                'area_kind' => 'official',
                'canonical_name' => $node['name'],
                'parent_id' => $parent?->id,
                'rank' => $node['rank'],
                'status' => 'active',
            ]);
            $parent = $area;
        }

        $baseArea->update([
            'parent_id' => $parent->id,
            'governance_type' => 'local',
            'rank' => 900,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/groups');
        $response->assertOk();

        $response->assertViewHas('generalGroups', function ($groups): bool {
            $types = collect($groups)
                ->map(fn (Group $group): ?string => $group->governanceArea?->governance_type)
                ->values()
                ->all();

            return $types === [
                'local',
                'urban_region',
                'city',
                'section',
                'county',
                'province',
                'country',
                'continent',
                'global',
            ];
        });
    }

    public function test_election_portal_rejects_stale_branch_after_residence_transfer_and_accepts_current_branch(): void
    {
        $this->enableStageCD();

        ['user' => $user, 'area' => $oldArea, 'endpoint' => $oldEndpoint] = MembershipFixture::canonicalUser();

        $this->actingAs($user)->get('/groups')->assertOk();

        $oldGroup = Group::query()
            ->where('governance_area_id', $oldArea->id)
            ->where('dimension_key', 'public')
            ->where('dimension_value_key', 'public')
            ->firstOrFail();

        [$newEndpoint, $newArea] = $this->newResidenceBranch($oldEndpoint);

        app(ResidenceService::class)->transferPrimaryResidence(
            $user,
            $newEndpoint,
            $user,
            'stage_d_production_hardening',
        );

        $newGroup = Group::query()
            ->where('governance_area_id', $newArea->id)
            ->where('dimension_key', 'public')
            ->where('dimension_value_key', 'public')
            ->firstOrFail();

        $this->actingAs($user)->get(route('elections.portal', $oldGroup))->assertForbidden();
        $this->actingAs($user)->get(route('elections.portal', $newGroup))->assertOk();
    }

    public function test_current_election_center_excludes_stale_canonical_branch_after_residence_transfer(): void
    {
        $this->enableStageCD();

        ['user' => $user, 'area' => $oldArea, 'endpoint' => $oldEndpoint] = MembershipFixture::canonicalUser();
        $this->actingAs($user)->get('/groups')->assertOk();

        $oldGroup = Group::query()
            ->where('governance_area_id', $oldArea->id)
            ->where('dimension_key', 'public')
            ->where('dimension_value_key', 'public')
            ->firstOrFail();

        [$newEndpoint, $newArea] = $this->newResidenceBranch($oldEndpoint);

        app(ResidenceService::class)->transferPrimaryResidence(
            $user,
            $newEndpoint,
            $user,
            'stage_d_current_center_hardening',
        );

        $newGroup = Group::query()
            ->where('governance_area_id', $newArea->id)
            ->where('dimension_key', 'public')
            ->where('dimension_value_key', 'public')
            ->firstOrFail();

        foreach ([$oldGroup, $newGroup] as $group) {
            Election::create([
                'group_id' => $group->id,
                'starts_at' => now()->subHour(),
                'ends_at' => now()->addDay(),
                'is_closed' => false,
                'lifecycle_status' => 'open',
                'cycle_number' => 1,
            ]);
        }

        $groupIds = app(CurrentElectionCenterService::class)
            ->forUser($user)['systemic']
            ->pluck('group_id')
            ->all();

        $this->assertContains($newGroup->id, $groupIds);
        $this->assertNotContains($oldGroup->id, $groupIds);
    }

    public function test_current_systemic_election_center_excludes_active_legacy_membership_in_canonical_mode(): void
    {
        $this->enableStageCD();

        ['user' => $user, 'area' => $currentArea] = MembershipFixture::canonicalUser();
        $this->actingAs($user)->get('/groups')->assertOk();

        $currentGroup = Group::query()
            ->where('governance_area_id', $currentArea->id)
            ->where('dimension_key', 'public')
            ->where('dimension_value_key', 'public')
            ->firstOrFail();

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

        foreach ([$legacyGroup, $currentGroup] as $group) {
            Election::create([
                'group_id' => $group->id,
                'starts_at' => now()->subHour(),
                'ends_at' => now()->addDay(),
                'is_closed' => false,
                'lifecycle_status' => 'open',
                'cycle_number' => 1,
            ]);
        }

        $groupIds = app(CurrentElectionCenterService::class)
            ->forUser($user)['systemic']
            ->pluck('group_id')
            ->all();

        $this->assertContains($currentGroup->id, $groupIds);
        $this->assertNotContains($legacyGroup->id, $groupIds);
    }

    public function test_current_internal_election_center_excludes_stale_canonical_branch_after_residence_transfer(): void
    {
        $this->enableStageCD();

        ['user' => $user, 'area' => $oldArea, 'endpoint' => $oldEndpoint] = MembershipFixture::canonicalUser();
        $this->actingAs($user)->get('/groups')->assertOk();

        $oldGroup = Group::query()
            ->where('governance_area_id', $oldArea->id)
            ->where('dimension_key', 'public')
            ->where('dimension_value_key', 'public')
            ->firstOrFail();

        [$newEndpoint, $newArea] = $this->newResidenceBranch($oldEndpoint);

        app(ResidenceService::class)->transferPrimaryResidence(
            $user,
            $newEndpoint,
            $user,
            'stage_d_internal_center_hardening',
        );

        $newGroup = Group::query()
            ->where('governance_area_id', $newArea->id)
            ->where('dimension_key', 'public')
            ->where('dimension_value_key', 'public')
            ->firstOrFail();

        foreach ([$oldGroup, $newGroup] as $index => $group) {
            Poll::create([
                'group_id' => $group->id,
                'created_by' => $user->id,
                'question' => 'Internal election '.$index,
                'main_type' => 0,
                'is_active' => true,
                'expires_at' => now()->addDay(),
            ]);
        }

        $groupIds = app(CurrentElectionCenterService::class)
            ->forUser($user)['internal']
            ->pluck('group_id')
            ->all();

        $this->assertContains($newGroup->id, $groupIds);
        $this->assertNotContains($oldGroup->id, $groupIds);
    }

    private function newResidenceBranch(Location $oldEndpoint): array
    {
        $newEndpoint = Location::factory()->create([
            'parent_id' => $oldEndpoint->parent_id,
            'location_schema_id' => $oldEndpoint->location_schema_id,
            'location_type_id' => $oldEndpoint->location_type_id,
            'country_code' => 'IR',
            'name' => 'محله مرجع جدید',
            'canonical_name' => 'New Reference Neighborhood',
            'level' => $oldEndpoint->level,
            'status' => 'active',
        ]);

        $newArea = GovernanceArea::create([
            'key' => 'stage-d-current-area-'.uniqid('', true),
            'country_code' => 'IR',
            'governance_type' => 'local',
            'area_kind' => 'official',
            'canonical_name' => 'Current Area',
            'rank' => 900,
            'status' => 'active',
        ]);
        $newArea->locations()->attach($newEndpoint->id);

        return [$newEndpoint, $newArea];
    }

    private function enableStageCD(): void
    {
        config([
            'location-governance.runtime_enabled' => true,
            'location-governance.registration_enabled' => true,
            'location-governance.groups_enabled' => true,
            'location-governance.elections_enabled' => true,
        ]);
    }
}
