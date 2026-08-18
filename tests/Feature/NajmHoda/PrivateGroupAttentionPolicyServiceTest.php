<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\NajmHodaGroupAttentionSetting;
use App\Models\User;
use App\Services\NajmHoda\NajmHodaPrivateGroupActionItemCommandService;
use App\Services\NajmHoda\NajmHodaPrivateGroupAttentionPolicyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PrivateGroupAttentionPolicyServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('najm_hoda_group_attention_settings') || ! Schema::hasTable('najm_hoda_group_attention_events')) {
            Artisan::call('migrate', ['--path' => 'database/migrations/2026_08_17_044500_create_najm_hoda_group_attention_tables.php', '--force' => true]);
        }
    }

    public function test_policy_change_requires_confirmation_and_then_persists(): void
    {
        [$group, $manager] = $this->member(3);
        $service = app(NajmHodaPrivateGroupAttentionPolicyService::class);

        $preview = $service->intercept($manager, $this->ctx($group), 'هشدارهای نجم هدا را فوری کن', 55);
        $this->assertSame('awaiting_confirmation', $preview['status']);
        $this->assertFalse((bool) NajmHodaGroupAttentionSetting::where('group_id', $group->id)->value('enabled'));

        $done = $service->intercept($manager, $this->ctx($group), 'تأیید', 55);
        $this->assertSame('executed', $done['status']);
        $setting = NajmHodaGroupAttentionSetting::where('group_id', $group->id)->firstOrFail();
        $this->assertTrue((bool) $setting->enabled);
        $this->assertSame('immediate', $setting->digest_mode);
    }

    public function test_daily_time_and_due_window_parse_persian_digits(): void
    {
        [$group, $manager] = $this->member(3);
        $service = app(NajmHodaPrivateGroupAttentionPolicyService::class);

        $preview = $service->intercept($manager, $this->ctx($group), 'خلاصه روزانه ساعت ۹:۳۰ بده و موعد نزدیک را ۲۴ ساعت در نظر بگیر', 77);
        $this->assertSame('awaiting_confirmation', $preview['status']);
        $service->intercept($manager, $this->ctx($group), 'تایید', 77);

        $setting = NajmHodaGroupAttentionSetting::where('group_id', $group->id)->firstOrFail();
        $this->assertSame('daily', $setting->digest_mode);
        $this->assertSame('09:30', $setting->preferred_time);
        $this->assertSame(24, (int) $setting->due_soon_hours);
    }

    public function test_runtime_action_item_interceptor_routes_policy_intent_before_general_agent(): void
    {
        [$group, $manager] = $this->member(3);
        $response = app(NajmHodaPrivateGroupActionItemCommandService::class)->intercept(
            $manager,
            $this->ctx($group),
            'تنظیمات پیگیری نجم هدا چیست؟',
            91
        );

        $this->assertIsArray($response);
        $this->assertTrue((bool) ($response['private_group_attention_policy'] ?? false));
        $this->assertSame('policy_status', $response['status']);
    }

    public function test_regular_member_cannot_change_policy(): void
    {
        [$group, $member] = $this->member(1);
        $response = app(NajmHodaPrivateGroupAttentionPolicyService::class)->intercept(
            $member,
            $this->ctx($group),
            'پیگیری فعال را روشن کن',
            101
        );

        $this->assertSame('blocked', $response['status']);
    }

    private function member(int $role): array
    {
        $group = Group::create(['name' => 'Policy test group', 'is_open' => 1]);
        $user = User::create([
            'email' => uniqid('attention-policy-', true) . '@example.test',
            'password' => Hash::make('password'),
            'status' => 1,
            'first_name' => 'کاربر',
            'last_name' => 'آزمایشی',
            'is_system' => false,
        ]);
        GroupUser::create(['group_id' => $group->id, 'user_id' => $user->id, 'role' => $role, 'status' => 1]);
        return [$group, $user];
    }

    private function ctx(Group $group): array
    {
        return ['page_kind' => 'group_chat', 'resource_id' => $group->id, 'resource' => ['id' => $group->id, 'type' => 'group']];
    }
}
