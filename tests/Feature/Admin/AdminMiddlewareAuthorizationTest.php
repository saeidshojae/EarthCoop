<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\FounderOperationsMiddleware;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminMiddlewareAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_scoped_application_role_can_enter_generic_admin_but_not_founder_operations(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $role = Role::query()->create([
            'name' => 'Support',
            'slug' => 'support',
            'description' => 'Scoped support role',
            'is_system' => false,
            'order' => 10,
        ]);
        $user->roles()->attach($role->id);
        $this->actingAs($user);

        $generic = app(AdminMiddleware::class)->handle(
            Request::create('/admin/tickets', 'GET'),
            fn () => response('allowed', 200)
        );
        $this->assertSame(200, $generic->getStatusCode());

        $request = Request::create('/admin/najm-hoda/founder-ops', 'GET');
        $request->setUserResolver(fn () => $user);
        $founder = app(FounderOperationsMiddleware::class)->handle(
            $request,
            fn () => response('allowed', 200)
        );

        $this->assertTrue($founder->isRedirect());
        $this->assertStringEndsWith('/home', $founder->headers->get('Location'));
    }

    public function test_explicit_admin_flag_grants_founder_operations_access(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $request = Request::create('/admin/najm-hoda/founder-ops', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = app(FounderOperationsMiddleware::class)->handle(
            $request,
            fn () => response('allowed', 200)
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('allowed', $response->getContent());
    }

    public function test_super_admin_role_grants_founder_operations_access(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $role = Role::query()->create([
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'description' => 'Global administration role',
            'is_system' => true,
            'order' => 1,
        ]);
        $user->roles()->attach($role->id);
        $request = Request::create('/admin/najm-hoda/founder-ops', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = app(FounderOperationsMiddleware::class)->handle(
            $request,
            fn () => response('allowed', 200)
        );

        $this->assertSame(200, $response->getStatusCode());
    }
}
