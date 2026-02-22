<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\Runtime\InMemoryRuntimeEventBus;
use App\Services\NajmHoda\Runtime\NajmHodaDelegatedPermissionService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DelegatedPermissionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'najm-hoda.runtime.autonomy.permissioning_v2.enabled' => true,
            'najm-hoda.runtime.autonomy.permissioning_v2.default_expiry_minutes' => 60,
        ]);
        Cache::flush();
    }

    public function test_grant_and_authorize_user_delegation(): void
    {
        $service = new NajmHodaDelegatedPermissionService(new InMemoryRuntimeEventBus(100));
        $grant = $service->grant([
            'principal_type' => 'user',
            'principal_id' => '15',
            'action' => 'run_ops_monitor',
            'scope' => 'autonomy:run_ops_monitor',
        ]);

        $this->assertTrue((bool) ($grant['success'] ?? false));

        $auth = $service->authorize(15, 'run_ops_monitor', 'autonomy:run_ops_monitor');
        $this->assertTrue((bool) ($auth['allowed'] ?? false));
    }

    public function test_authorize_denies_when_no_delegation_found(): void
    {
        $service = new NajmHodaDelegatedPermissionService(new InMemoryRuntimeEventBus(100));
        $auth = $service->authorize(99, 'run_ops_monitor', 'autonomy:run_ops_monitor');
        $this->assertFalse((bool) ($auth['allowed'] ?? true));
        $this->assertSame('no_active_delegation', (string) ($auth['reason'] ?? ''));
    }

    public function test_authorize_allows_role_delegation_with_context_roles(): void
    {
        $service = new NajmHodaDelegatedPermissionService(new InMemoryRuntimeEventBus(100));
        $grant = $service->grant([
            'principal_type' => 'role',
            'principal_id' => 'ops-admin',
            'action' => 'run_ops_monitor',
            'scope' => 'autonomy:run_ops_monitor',
        ]);

        $this->assertTrue((bool) ($grant['success'] ?? false));

        $auth = $service->authorize(42, 'run_ops_monitor', 'autonomy:run_ops_monitor', [
            'role_slugs' => ['member', 'ops-admin'],
        ]);
        $this->assertTrue((bool) ($auth['allowed'] ?? false));
    }

    public function test_authorize_allows_group_delegation_with_context_groups(): void
    {
        $service = new NajmHodaDelegatedPermissionService(new InMemoryRuntimeEventBus(100));
        $grant = $service->grant([
            'principal_type' => 'group',
            'principal_id' => '77',
            'action' => 'run_ops_monitor',
            'scope' => 'autonomy:run_ops_monitor',
        ]);

        $this->assertTrue((bool) ($grant['success'] ?? false));

        $auth = $service->authorize(42, 'run_ops_monitor', 'autonomy:run_ops_monitor', [
            'group_ids' => [12, 77],
        ]);
        $this->assertTrue((bool) ($auth['allowed'] ?? false));
    }
}
