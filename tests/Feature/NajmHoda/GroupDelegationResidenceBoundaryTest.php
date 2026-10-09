<?php

namespace Tests\Feature\NajmHoda;

use App\Models\GovernanceArea;
use App\Models\Group;
use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaDelegatedPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\LocationGovernance\MembershipFixture;
use Tests\TestCase;

final class GroupDelegationResidenceBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_canonical_membership_from_previous_residence_cannot_authorize_delegation(): void
    {
        config([
            'cache.default' => 'array',
            'location-governance.groups_enabled' => true,
            'najm-hoda.runtime.autonomy.permissioning_v2.enabled' => true,
        ]);
        Cache::flush();

        ['user' => $user, 'area' => $currentArea] = MembershipFixture::canonicalUser();

        $formerArea = GovernanceArea::query()->create([
            'key' => 'prior-residence-area',
            'country_code' => 'IR',
            'governance_type' => 'city',
            'area_kind' => 'official',
            'canonical_name' => 'Former residence',
            'rank' => 10,
            'status' => 'active',
        ]);
        $formerGroup = Group::query()->create([
            'name' => 'Stale canonical public group',
            'group_type' => '0',
            'governance_area_id' => $formerArea->id,
            'dimension_key' => 'public',
            'dimension_value_key' => 'public',
            'is_open' => 1,
        ]);
        $user->groups()->attach($formerGroup->id, ['status' => 1, 'role' => 0]);

        $service = new NajmHodaDelegatedPermissionService(new InMemoryRuntimeEventBus(100));
        $service->grant([
            'principal_type' => 'group',
            'principal_id' => (string) $formerGroup->id,
            'action' => 'run_ops_monitor',
            'scope' => 'autonomy:run_ops_monitor',
        ]);

        $this->assertNotSame($currentArea->id, $formerArea->id);
        $this->assertFalse((bool) ($service->authorize($user->id, 'run_ops_monitor', 'autonomy:run_ops_monitor')['allowed'] ?? true));
    }
}
