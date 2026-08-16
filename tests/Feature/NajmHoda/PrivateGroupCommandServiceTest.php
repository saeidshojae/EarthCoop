<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\Poll;
use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Services\NajmHoda\NajmHodaPrivateGroupCommandService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PrivateGroupCommandServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'najm-hoda.enabled' => true,
            'najm-hoda.group_assistant.enabled' => true,
            'najm-hoda.group_assistant.action_executor.enabled' => true,
            'najm-hoda.group_assistant.action_executor.dry_run' => false,
            'najm-hoda.group_assistant.action_executor.propose_before_execute' => false,
            'najm-hoda.group_assistant.action_executor.allow_create_poll' => true,
            'najm-hoda.group_assistant.action_executor.permitted_roles' => [2, 3],
        ]);
    }

    public function test_guidance_question_is_not_misclassified_as_execution_request(): void
    {
        [$group, $manager] = $this->makeGroupAndUser(3);

        $response = app(NajmHodaPrivateGroupCommandService::class)->intercept(
            $manager,
            $this->pageContext($group),
            'دقیقاً چطور یک نظرسنجی ایجاد کنم؟',
            1001
        );

        $this->assertNull($response);
        $this->assertSame(0, Poll::query()->where('group_id', $group->id)->count());
    }

    public function test_manager_command_requires_confirmation_then_publishes_only_result(): void
    {
        [$group, $manager] = $this->makeGroupAndUser(3);
        $service = app(NajmHodaPrivateGroupCommandService::class);
        $command = 'یک نظرسنجی بساز | مهلت: 3 | سوال: آیا برنامه هفتگی تصویب شود؟ | گزینه‌ها: موافق، مخالف';

        $proposal = $service->intercept($manager, $this->pageContext($group), $command, 1002);

        $this->assertIsArray($proposal);
        $this->assertSame('awaiting_confirmation', $proposal['action_status']);
        $this->assertSame(0, Poll::query()->where('group_id', $group->id)->count(), 'Planning must not mutate the group.');

        $executed = $service->intercept($manager, $this->pageContext($group), 'تأیید', 1002);

        $this->assertIsArray($executed);
        $this->assertSame('executed', $executed['action_status']);
        $this->assertStringContainsString('گفت‌وگوی مدیریتی فقط در همین چت خصوصی باقی ماند', $executed['message']);

        $poll = Poll::query()->where('group_id', $group->id)->latest('id')->firstOrFail();
        $this->assertSame('آیا برنامه هفتگی تصویب شود؟', $poll->question);
        $this->assertSame(1, (int) $poll->main_type);
        $this->assertCount(2, $poll->options()->get());

        $bot = User::query()
            ->where('email', config('najm-hoda.group_assistant.bot_email', 'najm-hoda-bot@local.invalid'))
            ->firstOrFail();

        $this->assertTrue($bot->isSystemIdentity());
        $this->assertTrue($group->systemUsers()->whereKey($bot->id)->exists());
        $this->assertFalse($group->users()->whereKey($bot->id)->exists());
        $this->assertFalse(Account::query()->where('user_id', $bot->id)->exists());
    }

    public function test_non_leadership_member_cannot_execute_manager_group_action(): void
    {
        [$group, $member] = $this->makeGroupAndUser(1);

        $response = app(NajmHodaPrivateGroupCommandService::class)->intercept(
            $member,
            $this->pageContext($group),
            'یک نظرسنجی بساز | سوال: تست؟ | گزینه‌ها: بله، خیر',
            1003
        );

        $this->assertIsArray($response);
        $this->assertSame('blocked', $response['action_status']);
        $this->assertSame(0, Poll::query()->where('group_id', $group->id)->count());
    }

    /** @return array{0:Group,1:User} */
    private function makeGroupAndUser(int $role): array
    {
        $group = Group::create([
            'name' => 'Najm Hoda private command test ' . uniqid('', true),
            'is_open' => 1,
        ]);

        $user = User::create([
            'email' => uniqid('najm-hoda-test-', true) . '@example.test',
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
            'resource_type' => 'group',
            'resource_id' => $group->id,
            'resource' => [
                'type' => 'group',
                'id' => $group->id,
            ],
        ];
    }
}
