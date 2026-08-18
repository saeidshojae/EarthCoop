<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupSession;
use App\Models\GroupUser;
use App\Models\Message;
use App\Models\NajmHodaGroupMeetingMinute;
use App\Models\User;
use App\Services\NajmHoda\NajmHodaPrivateGroupActionItemCommandService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PrivateGroupMeetingLifecycleCommandServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_manager_can_end_active_meeting_and_receive_grounded_minutes_draft(): void
    {
        [$group, $manager] = $this->makeGroupAndUser(3);
        $session = GroupSession::create([
            'group_id' => $group->id,
            'created_by' => $manager->id,
            'title' => 'نشست تست چرخه',
            'subject' => 'بررسی موضوع',
            'agenda' => 'بحث و جمع‌بندی',
            'starts_at' => now()->subMinutes(20),
            'started_at' => now()->subMinutes(20),
            'status' => 'active',
        ]);
        $group->update(['is_open' => false]);

        Message::create([
            'group_id' => $group->id,
            'user_id' => $manager->id,
            'message' => 'این پیام داخل نشست رسمی ثبت شده است.',
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        $service = app(NajmHodaPrivateGroupActionItemCommandService::class);
        $preview = $service->intercept($manager, $this->pageContext($group), 'جلسه را پایان بده', 3301);
        $this->assertSame('awaiting_confirmation', $preview['action_status']);
        $this->assertSame('active', $session->fresh()->status);

        $confirmed = $service->intercept($manager, $this->pageContext($group), 'تأیید', 3301);
        $this->assertSame('executed', $confirmed['action_status']);
        $this->assertStringContainsString('پیش‌نویس صورتجلسه', $confirmed['message']);
        $this->assertSame('ended', $session->fresh()->status);
        $this->assertTrue((bool) $group->fresh()->is_open);

        $minute = NajmHodaGroupMeetingMinute::query()->where('group_session_id', $session->id)->firstOrFail();
        $this->assertSame('draft', $minute->status);
        $this->assertSame(1, (int) data_get($minute->evidence_snapshot, 'counts.messages'));
    }

    public function test_minutes_view_is_read_only_and_approval_requires_second_confirmation(): void
    {
        [$group, $manager] = $this->makeGroupAndUser(3);
        $session = GroupSession::create([
            'group_id' => $group->id,
            'created_by' => $manager->id,
            'title' => 'نشست صورتجلسه',
            'starts_at' => now()->subHour(),
            'started_at' => now()->subHour(),
            'ended_at' => now()->subMinutes(10),
            'ended_by' => $manager->id,
            'status' => 'ended',
        ]);
        $minute = app(\App\Services\NajmHoda\NajmHodaGroupMeetingMinutesService::class)->generateDraft($session, $manager);

        $service = app(NajmHodaPrivateGroupActionItemCommandService::class);
        $view = $service->intercept($manager, $this->pageContext($group), 'پیش‌نویس صورتجلسه را نشان بده', 3302);
        $this->assertSame('minutes', $view['action_status']);
        $this->assertSame('draft', $minute->fresh()->status);

        $proposal = $service->intercept($manager, $this->pageContext($group), 'صورتجلسه را تایید کن', 3302);
        $this->assertSame('awaiting_confirmation', $proposal['action_status']);
        $this->assertSame('draft', $minute->fresh()->status);

        $approved = $service->intercept($manager, $this->pageContext($group), 'تأیید', 3302);
        $this->assertSame('executed', $approved['action_status']);
        $this->assertSame('approved', $minute->fresh()->status);
        $this->assertSame((int) $manager->id, (int) $minute->fresh()->approved_by);
    }

    public function test_regular_member_cannot_end_or_approve_official_meeting(): void
    {
        [$group, $member] = $this->makeGroupAndUser(1);
        GroupSession::create([
            'group_id' => $group->id,
            'created_by' => $member->id,
            'title' => 'نشست محافظت‌شده',
            'starts_at' => now()->subMinutes(10),
            'started_at' => now()->subMinutes(10),
            'status' => 'active',
        ]);

        $response = app(NajmHodaPrivateGroupActionItemCommandService::class)->intercept(
            $member,
            $this->pageContext($group),
            'جلسه را پایان بده',
            3303
        );
        $this->assertSame('blocked', $response['action_status']);
    }

    /** @return array{0:Group,1:User} */
    private function makeGroupAndUser(int $role): array
    {
        $group = Group::create([
            'name' => 'Meeting lifecycle test ' . uniqid('', true),
            'is_open' => 1,
        ]);
        $user = User::create([
            'email' => uniqid('meeting-lifecycle-', true) . '@example.test',
            'password' => Hash::make('password'),
            'status' => 1,
            'first_name' => 'Test',
            'last_name' => 'Manager',
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
