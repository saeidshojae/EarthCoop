<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupSession;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\NajmHoda\NajmHodaPrivateGroupActionItemCommandService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PrivateGroupMeetingCommandServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_manager_meeting_command_requires_confirmation_and_creates_exact_session(): void
    {
        // Keep the scheduled-meeting contract deterministic. The requested
        // 18:30 session must remain in the future regardless of when CI runs.
        Carbon::setTestNow(Carbon::parse('2026-08-20 12:00:00'));

        [$group, $manager] = $this->makeGroupAndUser(3);
        $service = app(NajmHodaPrivateGroupActionItemCommandService::class);
        $message = 'یک نشست تنظیم کن | عنوان: جلسه بودجه | موضوع: بودجه ماه آینده | دستور جلسه: بررسی هزینه‌ها | زمان: 2026-08-20 18:30';

        $proposal = $service->intercept($manager, $this->pageContext($group), $message, 2201);

        $this->assertIsArray($proposal);
        $this->assertSame('awaiting_confirmation', $proposal['action_status']);
        $this->assertStringContainsString('جلسه بودجه', $proposal['message']);
        $this->assertSame(0, GroupSession::query()->where('group_id', $group->id)->count());

        $confirmed = $service->intercept($manager, $this->pageContext($group), 'تأیید', 2201);

        $this->assertIsArray($confirmed);
        $this->assertSame('executed', $confirmed['action_status']);
        $session = GroupSession::query()->where('group_id', $group->id)->firstOrFail();
        $this->assertSame('جلسه بودجه', $session->title);
        $this->assertSame('بودجه ماه آینده', $session->subject);
        $this->assertSame('بررسی هزینه‌ها', $session->agenda);
        $this->assertSame('scheduled', $session->status);
    }

    public function test_manager_can_start_immediate_official_meeting_from_private_widget(): void
    {
        [$group, $manager] = $this->makeGroupAndUser(3);
        $service = app(NajmHodaPrivateGroupActionItemCommandService::class);

        $proposal = $service->intercept(
            $manager,
            $this->pageContext($group),
            'الان یک جلسه رسمی برگزار کن | عنوان: جلسه فوری | دستور جلسه: بررسی رخداد',
            2202
        );
        $this->assertSame('awaiting_confirmation', $proposal['action_status']);

        $confirmed = $service->intercept($manager, $this->pageContext($group), 'تایید', 2202);
        $this->assertSame('executed', $confirmed['action_status']);

        $session = GroupSession::query()->where('group_id', $group->id)->latest('id')->firstOrFail();
        $this->assertSame('active', $session->status);
        $this->assertNotNull($session->started_at);
        $this->assertFalse((bool) $group->fresh()->is_open);
    }

    public function test_regular_member_cannot_create_official_meeting(): void
    {
        [$group, $member] = $this->makeGroupAndUser(1);

        $response = app(NajmHodaPrivateGroupActionItemCommandService::class)->intercept(
            $member,
            $this->pageContext($group),
            'الان یک نشست تنظیم کن | عنوان: نشست غیرمجاز',
            2203
        );

        $this->assertIsArray($response);
        $this->assertSame('blocked', $response['action_status']);
        $this->assertSame(0, GroupSession::query()->where('group_id', $group->id)->count());
    }

    /** @return array{0:Group,1:User} */
    private function makeGroupAndUser(int $role): array
    {
        $group = Group::create([
            'name' => 'Meeting widget test ' . uniqid('', true),
            'is_open' => 1,
        ]);

        $user = User::create([
            'email' => uniqid('meeting-widget-', true) . '@example.test',
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
