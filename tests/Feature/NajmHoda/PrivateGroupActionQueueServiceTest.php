<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\NajmHodaGroupActionItem;
use App\Models\User;
use App\Services\NajmHoda\NajmHodaPrivateGroupActionQueueService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PrivateGroupActionQueueServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    public function test_leader_can_list_open_queue_without_model_or_internal_ids(): void
    {
        [$group, $manager] = $this->seedMember(3, 'مدیر', 'گروه');
        NajmHodaGroupActionItem::create([
            'group_id' => $group->id,
            'title' => 'تهیه گزارش جلسه',
            'priority' => 'high',
            'status' => 'open',
        ]);
        NajmHodaGroupActionItem::create([
            'group_id' => $group->id,
            'title' => 'کار تمام شده',
            'priority' => 'medium',
            'status' => 'done',
        ]);

        $response = app(NajmHodaPrivateGroupActionQueueService::class)->intercept(
            $manager,
            $this->pageContext($group),
            'کارهای باز گروه چیه؟',
            11
        );

        $this->assertIsArray($response);
        $this->assertSame('listed', $response['status']);
        $this->assertStringContainsString('تهیه گزارش جلسه', $response['message']);
        $this->assertStringNotContainsString('کار تمام شده', $response['message']);
        $this->assertStringContainsString('لازم نیست شناسه بدانید', $response['message']);
    }

    public function test_marking_last_action_done_requires_confirmation_and_audits_change(): void
    {
        [$group, $manager] = $this->seedMember(3, 'مدیر', 'گروه');
        $item = NajmHodaGroupActionItem::create([
            'group_id' => $group->id,
            'title' => 'تماس با تامین‌کننده',
            'priority' => 'medium',
            'status' => 'open',
        ]);

        $service = app(NajmHodaPrivateGroupActionQueueService::class);
        $preview = $service->intercept(
            $manager,
            $this->pageContext($group),
            'آخرین مورد اقدام را انجام‌شده کن',
            12
        );

        $this->assertSame('awaiting_confirmation', $preview['status']);
        $this->assertStringContainsString('تماس با تامین‌کننده', $preview['message']);
        $this->assertSame('open', $item->fresh()->status);

        $confirmed = $service->intercept($manager, $this->pageContext($group), 'تأیید', 12);

        $this->assertSame('executed', $confirmed['status']);
        $item->refresh();
        $this->assertSame('done', $item->status);
        $this->assertSame($manager->id, (int) data_get($item->meta, 'management_history.0.changed_by_user_id'));
        $this->assertSame('open', data_get($item->meta, 'management_history.0.before.status'));
        $this->assertSame('done', data_get($item->meta, 'management_history.0.changes.status'));
    }

    public function test_manager_can_assign_by_member_name_without_knowing_user_id(): void
    {
        [$group, $manager] = $this->seedMember(3, 'مدیر', 'گروه');
        [, $member] = $this->seedMemberInExistingGroup($group, 1, 'علی', 'رضایی');
        $item = NajmHodaGroupActionItem::create([
            'group_id' => $group->id,
            'title' => 'پیگیری مجوز',
            'priority' => 'medium',
            'status' => 'open',
        ]);

        $service = app(NajmHodaPrivateGroupActionQueueService::class);
        $preview = $service->intercept(
            $manager,
            $this->pageContext($group),
            'کار «پیگیری مجوز» را به علی رضایی بسپار',
            13
        );

        $this->assertSame('awaiting_confirmation', $preview['status']);
        $this->assertStringContainsString('علی رضایی', $preview['message']);
        $this->assertNull($item->fresh()->assigned_user_id);

        $service->intercept($manager, $this->pageContext($group), 'تأیید', 13);
        $item->refresh();

        $this->assertSame($member->id, (int) $item->assigned_user_id);
        $this->assertSame('علی رضایی', $item->assignee_name);
    }

    public function test_non_leadership_member_cannot_read_or_mutate_action_queue(): void
    {
        [$group, $member] = $this->seedMember(1, 'عضو', 'عادی');
        NajmHodaGroupActionItem::create([
            'group_id' => $group->id,
            'title' => 'مورد محرمانه مدیریتی',
            'priority' => 'high',
            'status' => 'open',
        ]);

        $response = app(NajmHodaPrivateGroupActionQueueService::class)->intercept(
            $member,
            $this->pageContext($group),
            'صف اقدام گروه را نشان بده',
            14
        );

        $this->assertSame('blocked', $response['status']);
        $this->assertStringNotContainsString('مورد محرمانه مدیریتی', $response['message']);
    }

    /** @return array{0:Group,1:User} */
    private function seedMember(int $role, string $firstName, string $lastName): array
    {
        $group = Group::create(['name' => 'Action queue management group', 'is_open' => 1]);
        return $this->seedMemberInExistingGroup($group, $role, $firstName, $lastName);
    }

    /** @return array{0:Group,1:User} */
    private function seedMemberInExistingGroup(Group $group, int $role, string $firstName, string $lastName): array
    {
        $user = User::create([
            'email' => uniqid('queue-member-', true) . '@example.test',
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

    /** @return array<string,mixed> */
    private function pageContext(Group $group): array
    {
        return [
            'page_kind' => 'group_chat',
            'resource_id' => $group->id,
            'resource' => ['id' => $group->id, 'type' => 'group'],
        ];
    }
}
