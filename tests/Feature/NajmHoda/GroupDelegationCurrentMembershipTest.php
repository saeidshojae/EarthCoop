<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\User;
use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaDelegatedPermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class GroupDelegationCurrentMembershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.autonomy.permissioning_v2.enabled' => true,
        ]);
        Cache::flush();
    }

    public function test_group_delegation_requires_current_nonexpired_membership(): void
    {
        $user = User::factory()->create();
        $group = Group::query()->create([
            'name' => 'Delegation scope group',
            'group_type' => '0',
            'is_open' => 1,
        ]);
        $service = new NajmHodaDelegatedPermissionService(new InMemoryRuntimeEventBus(100));
        $this->assertTrue((bool) $service->grant([
            'principal_type' => 'group',
            'principal_id' => (string) $group->id,
            'action' => 'run_ops_monitor',
            'scope' => 'autonomy:run_ops_monitor',
        ])['success']);

        $user->groups()->attach($group->id, ['role' => 0, 'status' => 0]);
        $this->assertFalse((bool) $service->authorize($user->id, 'run_ops_monitor', 'autonomy:run_ops_monitor')['allowed']);

        $user->groups()->updateExistingPivot($group->id, ['status' => 1, 'expired' => now()->subDay()]);
        $this->assertFalse((bool) $service->authorize($user->id, 'run_ops_monitor', 'autonomy:run_ops_monitor')['allowed']);

        $user->groups()->updateExistingPivot($group->id, ['status' => 1, 'expired' => null]);
        $this->assertTrue((bool) $service->authorize($user->id, 'run_ops_monitor', 'autonomy:run_ops_monitor')['allowed']);
    }
}
