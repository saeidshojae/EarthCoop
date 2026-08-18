<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\NajmHodaGroupActionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GroupActionQueuePanelTest extends TestCase
{
    use DatabaseTransactions;

    public function test_group_action_queue_panel_declares_grounded_management_contract(): void
    {
        $source = file_get_contents(resource_path('views/groups/najm-hoda-panel.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('صف اقدام نجم‌هدا', $source);
        $this->assertStringContainsString('group-hoda-stat-unassigned', $source);
        $this->assertStringContainsString('group-hoda-stat-urgent', $source);
        $this->assertStringContainsString("data_get(\$item->meta, 'source')", $source);
        $this->assertStringContainsString("data_get(\$item->meta, 'evidence')", $source);
        $this->assertStringContainsString('management_history', $source);
        $this->assertStringContainsString('groups.najm-hoda.action-items', $source);
    }

    public function test_regular_member_cannot_open_group_najm_hoda_panel(): void
    {
        [$group, $member] = $this->seedMember(1, 'عضو', 'عادی');

        $this->actingAs($member)
            ->get(route('groups.najm-hoda.panel', $group))
            ->assertForbidden();
    }

    public function test_panel_update_endpoint_changes_queue_item_with_active_group_member_assignee(): void
    {
        [$group, $manager] = $this->seedMember(3, 'مدیر', 'گروه');
        [, $assignee] = $this->seedMemberInExistingGroup($group, 1, 'علی', 'رضایی');
        $item = NajmHodaGroupActionItem::create([
            'group_id' => $group->id,
            'title' => 'پیگیری مجوز',
            'priority' => 'medium',
            'status' => 'open',
        ]);

        $response = $this->actingAs($manager)->putJson(
            route('groups.najm-hoda.action-items.update', [$group, $item]),
            [
                'status' => 'in_progress',
                'priority' => 'high',
                'assigned_user_id' => $assignee->id,
                'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
            ]
        );

        $response->assertOk()->assertJsonPath('status', 'success');
        $item->refresh();
        $this->assertSame('in_progress', $item->status);
        $this->assertSame('high', $item->priority);
        $this->assertSame($assignee->id, (int) $item->assigned_user_id);
        $this->assertSame('علی رضایی', $item->assignee_name);
        $this->assertNotNull($item->due_at);
    }

    /** @return array{0:Group,1:User} */
    private function seedMember(int $role, string $firstName, string $lastName): array
    {
        $group = Group::create(['name' => 'Najm Hoda panel group', 'is_open' => 1]);
        return $this->seedMemberInExistingGroup($group, $role, $firstName, $lastName);
    }

    /** @return array{0:Group,1:User} */
    private function seedMemberInExistingGroup(Group $group, int $role, string $firstName, string $lastName): array
    {
        $user = User::create([
            'email' => uniqid('panel-member-', true) . '@example.test',
            'password' => Hash::make('password'),
            'status' => 1,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'is_system' => false,
        ]);
        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 1,
        ]);
        return [$group, $user];
    }
}
