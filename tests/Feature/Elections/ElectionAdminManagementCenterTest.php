<?php

namespace Tests\Feature\Elections;

use App\Models\GroupSetting;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ElectionGroupSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ElectionAdminManagementCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_center_is_a_standalone_admin_route_and_renders(): void
    {
        $admin = User::factory()->create(['is_system' => false]);
        $role = Role::create([
            'name' => 'Election admin test operator',
            'slug' => 'election-admin-test-operator',
            'description' => 'Generic admin access for election management center rendering test.',
            'is_system' => false,
            'order' => 10,
        ]);
        $admin->roles()->attach($role->id);

        $this->actingAs($admin)
            ->get(route('admin.elections.dashboard'))
            ->assertOk()
            ->assertSee('مدیریت انتخابات')
            ->assertSee('سیاست‌ها و سطوح')
            ->assertSee('قراردادهای مسئولیت')
            ->assertSee('تعارض مسئولیت');

        $this->assertSame(
            'admin.elections.dashboard',
            Route::getRoutes()->match(request()->create('/admin/elections', 'GET'))->getName(),
        );
    }

    public function test_fresh_bootstrap_creates_every_supported_election_level_and_domain(): void
    {
        $this->seed(ElectionGroupSettingSeeder::class);

        $this->assertSame(55, GroupSetting::query()->count());
        $this->assertDatabaseHas('group_setting', ['level' => 'global']);
        $this->assertDatabaseHas('group_setting', ['level' => 'neighborhood_job']);
        $this->assertDatabaseHas('group_setting', ['level' => 'country_experience']);
        $this->assertDatabaseHas('group_setting', ['level' => 'city_age']);
        $this->assertDatabaseHas('group_setting', ['level' => 'province_gender']);
    }

    public function test_admin_sidebar_has_a_dedicated_election_management_entry(): void
    {
        $sidebar = file_get_contents(resource_path('views/admin/partials/sidebar.blade.php'));

        $this->assertStringContainsString("route('admin.elections.dashboard')", $sidebar);
        $this->assertStringContainsString('مدیریت انتخابات', $sidebar);
        $this->assertStringNotContainsString("request()->routeIs('admin.group.setting.*') ? 'bg-gray-700' : '' }}\"><i class=\"fas fa-cog", $sidebar);
    }
}
