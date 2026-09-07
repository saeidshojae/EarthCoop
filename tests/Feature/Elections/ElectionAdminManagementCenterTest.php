<?php

namespace Tests\Feature\Elections;

use App\Models\Election;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\GroupUser;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ElectionGroupSettingSeeder;
use Database\Seeders\ElectionResponsibilityContractSeeder;
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

    public function test_fresh_bootstrap_creates_every_supported_election_level_and_domain_with_canonical_defaults(): void
    {
        $this->seed(ElectionGroupSettingSeeder::class);

        $this->assertSame(55, GroupSetting::query()->count());
        $this->assertDatabaseHas('group_setting', [
            'level' => 'neighborhood',
            'manager_count' => 7,
            'inspector_count' => 3,
            'max_for_election' => 20,
            'election_time' => 30,
            'second_election_time' => 6,
        ]);
        $this->assertDatabaseHas('group_setting', ['level' => 'neighborhood_job']);
        $this->assertDatabaseHas('group_setting', ['level' => 'country_experience']);
        $this->assertDatabaseHas('group_setting', ['level' => 'city_age']);
        $this->assertDatabaseHas('group_setting', ['level' => 'province_gender']);
    }

    public function test_public_tab_contains_only_unsuffixed_public_levels(): void
    {
        $this->seed(ElectionGroupSettingSeeder::class);
        $admin = User::factory()->create(['is_system' => false]);
        $role = Role::create([
            'name' => 'Election settings operator',
            'slug' => 'election-settings-operator',
            'description' => 'Election settings test role.',
            'is_system' => false,
            'order' => 10,
        ]);
        $admin->roles()->attach($role->id);

        $response = $this->actingAs($admin)->get(route('admin.group.setting.index'));
        $response->assertOk();
        $settings = $response->viewData('groupSettings');

        $this->assertCount(11, $settings);
        $this->assertTrue($settings->every(fn (GroupSetting $setting) => ! str_contains($setting->level, '_')));
        $response->assertSee('فاصله چرخه');
        $response->assertSee('ماه');
        $response->assertSee('بازگشت به مدیریت انتخابات');
    }

    public function test_two_active_neighborhood_members_start_an_open_cycle_when_threshold_is_two_and_contracts_exist(): void
    {
        $this->seed(ElectionGroupSettingSeeder::class);
        $this->seed(ElectionResponsibilityContractSeeder::class);

        $setting = GroupSetting::query()->where('level', 'neighborhood')->firstOrFail();
        $setting->update([
            'manager_count' => 1,
            'inspector_count' => 1,
            'max_for_election' => 2,
            'election_status' => 1,
        ]);

        $group = Group::create([
            'name' => 'Two member public neighborhood',
            'group_type' => 'public',
            'location_level' => 'neighborhood',
            'address_id' => null,
        ]);
        $first = User::factory()->create(['is_system' => false]);
        $second = User::factory()->create(['is_system' => false]);
        foreach ([$first, $second] as $user) {
            GroupUser::create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'role' => 1,
                'status' => 1,
            ]);
        }

        $this->artisan('elections:process-lifecycle', ['--limit' => 500, '--fail-on-error' => true])
            ->assertSuccessful();

        $election = Election::query()->where('group_id', $group->id)->latest('id')->first();
        $this->assertNotNull($election);
        $this->assertSame('open', $election->lifecycle_status->value);
        $this->assertSame(2, (int) $election->policyVersion->start_threshold);
    }

    public function test_admin_sidebar_has_a_dedicated_election_management_entry(): void
    {
        $sidebar = file_get_contents(resource_path('views/admin/partials/sidebar.blade.php'));
        $layout = file_get_contents(resource_path('views/layouts/admin.blade.php'));

        $this->assertStringContainsString("route('admin.elections.dashboard')", $sidebar);
        $this->assertStringContainsString('مدیریت انتخابات', $sidebar);
        $this->assertStringContainsString('بازگشت به مدیریت انتخابات', $layout);
    }
}
