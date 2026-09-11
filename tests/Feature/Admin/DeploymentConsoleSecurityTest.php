<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DeploymentConsoleSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('deployment-console.enabled', true);
        config()->set('deployment-console.secret', 'temporary-deployment-secret');
    }

    public function test_console_is_unavailable_when_disabled(): void
    {
        config()->set('deployment-console.enabled', false);
        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)
            ->get('/admin/deployment-console')
            ->assertNotFound();
    }

    public function test_guest_is_redirected_before_console_access(): void
    {
        $this->get('/admin/deployment-console')
            ->assertRedirect('/home');
    }

    public function test_scoped_non_founder_admin_role_cannot_access_console(): void
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

        $this->actingAs($user)
            ->get('/admin/deployment-console')
            ->assertRedirect('/home');
    }

    public function test_enabled_founder_admin_can_open_console(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)
            ->get('/admin/deployment-console')
            ->assertOk();
    }

    public function test_wrong_deployment_secret_never_invokes_artisan(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        Artisan::shouldReceive('call')->never();

        $this->actingAs($user)
            ->from('/admin/deployment-console')
            ->post('/admin/deployment-console/run/migrate', [
                'deployment_secret' => 'wrong-secret',
                'confirmation' => 'MIGRATE',
            ])
            ->assertRedirect('/admin/deployment-console')
            ->assertSessionHasErrors('deployment_secret');
    }

    public function test_wrong_write_confirmation_never_invokes_artisan(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        Artisan::shouldReceive('call')->never();

        $this->actingAs($user)
            ->from('/admin/deployment-console')
            ->post('/admin/deployment-console/run/bootstrap', [
                'deployment_secret' => 'temporary-deployment-secret',
                'confirmation' => 'WRONG',
            ])
            ->assertRedirect('/admin/deployment-console')
            ->assertSessionHasErrors('confirmation');
    }

    public function test_unknown_operation_is_404_and_never_invokes_artisan(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        Artisan::shouldReceive('call')->never();

        $this->actingAs($user)
            ->post('/admin/deployment-console/run/arbitrary-command', [
                'deployment_secret' => 'temporary-deployment-secret',
            ])
            ->assertNotFound();
    }
}
