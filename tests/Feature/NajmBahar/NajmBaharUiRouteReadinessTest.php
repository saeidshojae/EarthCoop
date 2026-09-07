<?php

namespace Tests\Feature\NajmBahar;

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NajmBaharUiRouteReadinessTest extends TestCase
{
    public function test_member_uat_entry_routes_are_distinct_and_authenticated(): void
    {
        $expected = [
            'najm-bahar.dashboard' => 'najm-bahar/dashboard',
            'najm-bahar.wallet' => 'najm-bahar/wallet',
            'najm-bahar.transfer' => 'najm-bahar/transfer',
            'najm-bahar.reports' => 'najm-bahar/reports',
            'najm-bahar.sub-accounts.index' => 'najm-bahar/sub-accounts',
        ];

        foreach ($expected as $name => $uri) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Missing member UAT route: {$name}");
            $this->assertSame($uri, $route->uri());
            $this->assertContains(Authenticate::class, $route->gatherMiddleware());
        }
    }

    public function test_group_uat_entry_routes_are_authenticated_and_group_scoped(): void
    {
        $expected = [
            'groups.najm-bahar.dashboard' => 'groups/{group}/najm-bahar/dashboard',
            'groups.najm-bahar.wallet' => 'groups/{group}/najm-bahar/wallet',
            'groups.najm-bahar.transfer' => 'groups/{group}/najm-bahar/transfer',
            'groups.najm-bahar.reports' => 'groups/{group}/najm-bahar/reports',
            'groups.najm-bahar.sub-accounts.index' => 'groups/{group}/najm-bahar/sub-accounts',
        ];

        foreach ($expected as $name => $uri) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Missing group UAT route: {$name}");
            $this->assertSame($uri, $route->uri());
            $this->assertContains(Authenticate::class, $route->gatherMiddleware());
        }
    }

    public function test_admin_uat_entry_routes_are_admin_scoped_without_colliding_with_member_names(): void
    {
        $expected = [
            'admin.najm-bahar.dashboard' => 'admin/najm-bahar/dashboard',
            'admin.najm-bahar.settings.index' => 'admin/najm-bahar/settings',
            'admin.najm-bahar.system-accounts.index' => 'admin/najm-bahar/system-accounts',
            'admin.najm-bahar.accounts.index' => 'admin/najm-bahar/accounts',
            'admin.najm-bahar.audit-logs.index' => 'admin/najm-bahar/audit-logs',
        ];

        foreach ($expected as $name => $uri) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "Missing admin UAT route: {$name}");
            $this->assertSame($uri, $route->uri());
            $this->assertContains(AdminMiddleware::class, $route->gatherMiddleware());
        }

        $this->assertNotSame(
            Route::getRoutes()->getByName('najm-bahar.dashboard')?->uri(),
            Route::getRoutes()->getByName('admin.najm-bahar.dashboard')?->uri()
        );
    }
}
