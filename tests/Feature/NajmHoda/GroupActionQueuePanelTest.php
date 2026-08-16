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

    protected function setUp(): void
    {
        parent::setUp();

        // This feature test verifies the real panel route and queue markup, not
        // the asset pipeline or the global Najm Hoda widget. Keeping those out
        // avoids Windows test-runner stalls unrelated to the panel itself.
        $this->withoutVite();
        config()->set('najm-hoda.widget.enabled', false);
    }

    public function test_group_leader_panel_exposes_action_queue_and_grounded_metadata(): void
    {
        [$group, $manager] = $this->seedMember(3, 'مدیر', 'گروه');
        $item = NajmHodaGroupActionItem::create([
            'group_id' => $group->id,
            'title' => 'پیگیری گزارش جلسه',
            'details' => 'گزارش جلسه باید تکمیل شود.',
            'priority' => 'urgent',
            'status' => 'open',
            'meta' => [
                'source' => 'message:17',
                'evidence' => 'لطفاً گزارش جلسه را تکمیل کنید',
                'origin' => 'najm_hoda_semantic_proposal',
                'management_history' => [],
            ],
        ]);

        $response = $this->actingAs($manager)->get(route('groups.najm-hoda.panel', $group));
        $response->assertOk();

        // Avoid Laravel's normalization-heavy assertSee path on the very large
        // Persian unified-layout HTML. Raw containment is sufficient here and
        // keeps the regression test focused on actual emitted panel markup.
        $html = (string) $response->getContent();
        $this->assertTrue(str_contains($html, 'صف اقدام نجم‌هدا'));
        $this->assertTrue(str_contains($html, 'بدون مسئول'));
        $this->assertTrue(str_contains($html, 'فوری'));
        $this->assertTrue(str_contains($html, 'message:17'));
        $this->assertTrue(str_contains($html, 'لطفاً گزارش جلسه را تکمیل کنید'));

        $item->refresh();
        $this->assertSame('message:17', data_get($item->meta, 'source'));
        $this->assertSame('لطفاً گزارش جلسه را تکمیل کنید', data_get($item->meta, 'evidence'));
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
