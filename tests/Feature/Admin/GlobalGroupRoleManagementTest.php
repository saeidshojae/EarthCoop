<?php

namespace Tests\Feature\Admin;

use App\Models\Group;
use App\Models\GroupRoleBulkOperation;
use App\Models\GroupUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class GlobalGroupRoleManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_preview_filters_memberships_by_group_category_and_location_level(): void
    {
        $admin = $this->user(true);
        $payload = [
            'group_category' => 'specialized',
            'location_level' => 'province',
            'source_role' => 0,
            'target_role' => 1,
        ];
        $before = $this->actingAs($admin)
            ->postJson(route('admin.groups.global-roles.preview'), $payload)
            ->assertOk()
            ->json();
        $observer = $this->user();
        $matching = $this->group('1', 'province');
        $this->membership($matching, $observer, 0);
        $this->membership($this->group('0', 'province'), $this->user(), 0);
        $this->membership($this->group('2', 'country'), $this->user(), 0);

        $after = $this->actingAs($admin)
            ->postJson(route('admin.groups.global-roles.preview'), $payload)
            ->assertOk()
            ->json();
        $this->assertSame($before['groups'] + 1, $after['groups']);
        $this->assertSame($before['memberships'] + 1, $after['memberships']);
        $this->assertSame($before['will_apply'] + 1, $after['will_apply']);
    }

    public function test_operation_runs_in_batches_and_can_later_cancel_to_baseline(): void
    {
        $admin = $this->user(true);
        $observer = $this->user();
        $group = $this->group('1', 'province');
        $membership = $this->membership($group, $observer, 0);

        $operationId = $this->actingAs($admin)->postJson(route('admin.groups.global-roles.store'), [
            'group_category' => 'specialized',
            'location_level' => 'province',
            'source_role' => 0,
            'target_role' => 1,
            'duration_unit' => 'month',
            'duration_value' => 1,
        ])->assertCreated()->json('id');

        $this->actingAs($admin)
            ->postJson(route('admin.groups.global-roles.process', $operationId))
            ->assertOk()
            ->assertJson(['status' => 'completed']);
        $this->assertDatabaseHas('group_user', [
            'id' => $membership->id,
            'role' => 1,
            'role_override_active' => true,
            'role_override_original_role' => 0,
        ]);

        $cancelId = $this->actingAs($admin)->postJson(route('admin.groups.global-roles.store'), [
            'group_category' => 'specialized',
            'location_level' => 'province',
            'source_role' => 1,
            'target_role' => 0,
            'duration_unit' => 'day',
            'duration_value' => 1,
        ])->assertCreated()->json('id');

        $this->actingAs($admin)
            ->postJson(route('admin.groups.global-roles.process', $cancelId))
            ->assertOk()
            ->assertJson(['status' => 'completed']);
        $this->assertDatabaseHas('group_user', [
            'id' => $membership->id,
            'role' => 0,
            'role_override_active' => false,
            'role_override_expires_at' => null,
        ]);
        $this->assertNotNull(GroupRoleBulkOperation::find($operationId));
        $this->assertNotNull(GroupRoleBulkOperation::find($cancelId));
    }

    private function user(bool $admin = false): User
    {
        $user = User::create([
            'first_name' => 'Global',
            'last_name' => 'Role',
            'email' => 'global-role-' . Str::uuid() . '@example.test',
            'password' => bcrypt('password123'),
        ]);
        $user->forceFill(['is_admin' => $admin])->save();

        return $user;
    }

    private function group(string $type, string $level): Group
    {
        return Group::create([
            'group_type' => $type,
            'location_level' => $level,
            'name' => 'Global role ' . Str::uuid(),
            'is_open' => true,
        ]);
    }

    private function membership(Group $group, User $user, int $role): GroupUser
    {
        return GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 1,
        ]);
    }
}
