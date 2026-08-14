<?php

namespace Tests\Feature\Admin;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class GroupRoleManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_system_admin_can_change_an_existing_member_between_observer_and_active(): void
    {
        $admin = $this->makeUser(true);
        $member = $this->makeUser();
        $group = $this->makeGroup();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $member->id, 'role' => 0, 'status' => 1]);

        $this->actingAs($admin)
            ->put(route('admin.groups.updateRole', [$group, $member]), ['role' => 1, 'duration_unit' => 'day', 'duration_value' => 1])
            ->assertRedirect(route('admin.groups.manage', $group));
        $this->assertDatabaseHas('group_user', ['group_id' => $group->id, 'user_id' => $member->id, 'role' => 1]);

        $this->actingAs($admin)
            ->put(route('admin.groups.updateRole', [$group, $member]), ['role' => 0, 'duration_unit' => 'unlimited'])
            ->assertRedirect(route('admin.groups.manage', $group));
        $this->assertDatabaseHas('group_user', [
            'group_id' => $group->id,
            'user_id' => $member->id,
            'role' => 0,
            'role_override_active' => false,
            'role_override_expires_at' => null,
        ]);
    }

    public function test_system_admin_bulk_role_change_updates_only_real_group_members(): void
    {
        $admin = $this->makeUser(true);
        $first = $this->makeUser();
        $second = $this->makeUser();
        $outsider = $this->makeUser();
        $group = $this->makeGroup();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $first->id, 'role' => 0, 'status' => 1]);
        GroupUser::create(['group_id' => $group->id, 'user_id' => $second->id, 'role' => 0, 'status' => 1]);

        $this->actingAs($admin)->put(route('admin.groups.changeRoles', $group), [
            'users' => [$first->id, $second->id],
            'main_role' => 1,
            'duration_unit' => 'month',
            'duration_value' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('group_user', ['group_id' => $group->id, 'user_id' => $first->id, 'role' => 1]);
        $this->assertDatabaseHas('group_user', ['group_id' => $group->id, 'user_id' => $second->id, 'role' => 1]);
        $this->assertDatabaseHas('group_user', ['group_id' => $group->id, 'user_id' => $first->id, 'role_override_active' => true]);

        $this->actingAs($admin)->put(route('admin.groups.changeRoles', $group), [
            'users' => [$first->id, $outsider->id],
            'main_role' => 0,
            'duration_unit' => 'day',
            'duration_value' => 1,
        ])->assertStatus(422);
        $this->assertDatabaseHas('group_user', ['group_id' => $group->id, 'user_id' => $first->id, 'role' => 1]);
    }

    public function test_expired_override_is_lazily_restored_without_a_scheduler(): void
    {
        $admin = $this->makeUser(true);
        $member = $this->makeUser();
        $group = $this->makeGroup();
        $membership = GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $member->id,
            'role' => 1,
            'status' => 1,
            'role_override_active' => true,
            'role_override_original_role' => 0,
            'role_override_started_at' => now()->subHours(2),
            'role_override_expires_at' => now()->subMinute(),
            'role_override_changed_by' => $admin->id,
            'role_override_source' => 'system_admin',
        ]);

        app(\App\Services\TemporaryGroupRoleService::class)->restoreIfExpired($membership);

        $this->assertDatabaseHas('group_user', [
            'id' => $membership->id,
            'role' => 0,
            'role_override_active' => false,
            'role_override_expires_at' => null,
        ]);
    }

    public function test_system_admin_can_apply_an_unlimited_override(): void
    {
        $admin = $this->makeUser(true);
        $member = $this->makeUser();
        $group = $this->makeGroup();
        GroupUser::create(['group_id' => $group->id, 'user_id' => $member->id, 'role' => 0, 'status' => 1]);

        $this->actingAs($admin)->put(route('admin.groups.updateRole', [$group, $member]), [
            'role' => 1,
            'duration_unit' => 'unlimited',
        ])->assertRedirect(route('admin.groups.manage', $group));

        $this->assertDatabaseHas('group_user', [
            'group_id' => $group->id,
            'user_id' => $member->id,
            'role' => 1,
            'role_override_active' => true,
            'role_override_expires_at' => null,
        ]);
    }

    private function makeUser(bool $admin = false): User
    {
        $user = User::create([
            'first_name' => 'Role',
            'last_name' => 'Tester',
            'email' => 'group-role-' . Str::uuid() . '@example.test',
            'password' => bcrypt('password123'),
        ]);
        $user->forceFill(['is_admin' => $admin])->save();

        return $user;
    }

    private function makeGroup(): Group
    {
        return Group::create([
            'group_type' => 'general',
            'name' => 'Role management ' . Str::uuid(),
            'is_open' => true,
        ]);
    }
}
