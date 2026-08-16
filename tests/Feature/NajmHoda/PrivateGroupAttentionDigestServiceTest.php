<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\NajmHodaGroupActionItem;
use App\Models\User;
use App\Services\NajmHoda\NajmHodaPrivateGroupAttentionDigestService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PrivateGroupAttentionDigestServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_leader_receives_ranked_attention_digest_without_model(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-17 10:00:00'));
        [$group, $manager] = $this->seedMember(3, 'مدیر', 'گروه');

        NajmHodaGroupActionItem::create([
            'group_id' => $group->id,
            'title' => 'گزارش معوق',
            'priority' => 'high',
            'status' => 'open',
            'due_at' => now()->subHours(6),
        ]);
        NajmHodaGroupActionItem::create([
            'group_id' => $group->id,
            'title' => 'رفع مانع حقوقی',
            'priority' => 'medium',
            'status' => 'blocked',
            'assignee_name' => 'علی رضایی',
        ]);
        NajmHodaGroupActionItem::create([
            'group_id' => $group->id,
            'title' => 'تماس فوری',
            'priority' => 'urgent',
            'status' => 'open',
            'due_at' => now()->addHours(18),
            'assignee_name' => 'مریم احمدی',
        ]);
        NajmHodaGroupActionItem::create([
            'group_id' => $group->id,
            'title' => 'تعیین مسئول اجرا',
            'priority' => 'medium',
            'status' => 'open',
        ]);
        NajmHodaGroupActionItem::create([
            'group_id' => $group->id,
            'title' => 'کار تمام‌شده',
            'priority' => 'urgent',
            'status' => 'done',
            'due_at' => now()->subDay(),
        ]);

        $response = app(NajmHodaPrivateGroupAttentionDigestService::class)->intercept(
            $manager,
            $this->pageContext($group),
            'الان چه چیزهایی نیاز به توجه من دارد؟'
        );

        $this->assertIsArray($response);
        $this->assertSame('attention_digest', $response['status']);
        $this->assertStringContainsString('معوق: 1', $response['message']);
        $this->assertStringContainsString('مسدود: 1', $response['message']);
        $this->assertStringContainsString('فوری: 1', $response['message']);
        $this->assertStringContainsString('نزدیک موعد: 1', $response['message']);
        $this->assertStringContainsString('بدون مسئول: 2', $response['message']);
        $this->assertStringContainsString('گزارش معوق', $response['message']);
        $this->assertStringContainsString('رفع مانع حقوقی', $response['message']);
        $this->assertStringContainsString('تماس فوری', $response['message']);
        $this->assertStringContainsString('تعیین مسئول اجرا', $response['message']);
        $this->assertStringNotContainsString('کار تمام‌شده', $response['message']);

        $overduePos = mb_strpos($response['message'], 'گزارش معوق');
        $urgentPos = mb_strpos($response['message'], 'تماس فوری');
        $this->assertIsInt($overduePos);
        $this->assertIsInt($urgentPos);
        $this->assertLessThan($urgentPos, $overduePos, 'Overdue work should rank ahead of merely urgent near-due work.');
    }

    public function test_regular_member_cannot_read_attention_digest(): void
    {
        [$group, $member] = $this->seedMember(1, 'عضو', 'عادی');

        $response = app(NajmHodaPrivateGroupAttentionDigestService::class)->intercept(
            $member,
            $this->pageContext($group),
            'گزارش مدیریتی صف اقدام را بده'
        );

        $this->assertIsArray($response);
        $this->assertSame('blocked', $response['status']);
    }

    /** @return array{0:Group,1:User} */
    private function seedMember(int $role, string $firstName, string $lastName): array
    {
        $group = Group::create(['name' => 'Attention digest group', 'is_open' => 1]);
        $user = User::create([
            'email' => uniqid('attention-', true) . '@example.test',
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
