<?php

namespace Tests\Feature\NajmHoda;

use App\Models\Group;
use App\Models\GroupUser;
use App\Models\NajmHodaGroupActionItem;
use App\Models\NajmHodaGroupAttentionEvent;
use App\Models\NajmHodaGroupAttentionSetting;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Services\NajmHoda\NajmHodaGroupAttentionDeliveryService;
use App\Services\NajmHoda\NajmHodaGroupAttentionEvaluatorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GroupAttentionDeliveryServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('najm_hoda_group_attention_settings') || ! Schema::hasTable('najm_hoda_group_attention_events')) {
            Artisan::call('migrate', ['--path' => 'database/migrations/2026_08_17_044500_create_najm_hoda_group_attention_tables.php', '--force' => true]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_immediate_digest_targets_only_active_human_leadership_and_suppresses_repeat(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-17 10:00:00', 'Asia/Tehran'));
        Notification::fake();

        $group = Group::create(['name' => 'Delivery group', 'is_open' => 1]);
        $manager = $this->member($group, 3, 'مدیر', false);
        $inspector = $this->member($group, 2, 'بازرس', false);
        $member = $this->member($group, 1, 'عضو', false);
        $systemLeader = $this->member($group, 3, 'نجم هدا', true);

        NajmHodaGroupAttentionSetting::create([
            'group_id' => $group->id, 'enabled' => true, 'digest_mode' => 'immediate',
            'suppress_minutes' => 720, 'timezone' => 'Asia/Tehran', 'preferred_time' => '08:00',
        ]);
        NajmHodaGroupActionItem::create([
            'group_id' => $group->id, 'title' => 'پیگیری مجوز فوری', 'status' => 'open',
            'priority' => 'urgent', 'due_at' => now()->subHour(),
        ]);

        app(NajmHodaGroupAttentionEvaluatorService::class)->evaluateGroup($group);
        $result = app(NajmHodaGroupAttentionDeliveryService::class)->deliverGroup($group);

        $this->assertSame(1, $result['sent']);
        $this->assertSame(2, $result['recipients']);
        $this->assertSame(3, $result['events']);
        Notification::assertSentTo([$manager, $inspector], GenericNotification::class, fn (GenericNotification $n) =>
            $n->type === 'warning'
            && str_contains($n->message, 'پیگیری مجوز فوری')
            && str_contains($n->message, 'معوق')
            && str_contains($n->message, 'فوری')
            && str_contains($n->message, 'بدون مسئول')
            && ($n->context['source'] ?? null) === 'najm_hoda_proactive_attention'
        );
        Notification::assertNotSentTo($member, GenericNotification::class);
        Notification::assertNotSentTo($systemLeader, GenericNotification::class);
        $this->assertSame(3, NajmHodaGroupAttentionEvent::where('group_id', $group->id)->whereNotNull('last_notified_at')->count());

        $second = app(NajmHodaGroupAttentionDeliveryService::class)->deliverGroup($group);
        $this->assertSame(0, $second['sent']);
        $this->assertSame('no_pending_events', $second['reason']);
    }

    public function test_daily_digest_waits_for_preferred_time_and_sends_once_per_local_day(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-08-17 07:00:00', 'Asia/Tehran'));
        $group = Group::create(['name' => 'Daily delivery group', 'is_open' => 1]);
        $manager = $this->member($group, 3, 'مدیر روزانه', false);
        NajmHodaGroupAttentionSetting::create([
            'group_id' => $group->id, 'enabled' => true, 'digest_mode' => 'daily',
            'suppress_minutes' => 720, 'timezone' => 'Asia/Tehran', 'preferred_time' => '08:00',
        ]);
        NajmHodaGroupActionItem::create([
            'group_id' => $group->id, 'title' => 'کار بدون مسئول', 'status' => 'open', 'priority' => 'medium',
        ]);

        app(NajmHodaGroupAttentionEvaluatorService::class)->evaluateGroup($group);
        $delivery = app(NajmHodaGroupAttentionDeliveryService::class);
        $this->assertSame('daily_not_due', $delivery->deliverGroup($group)['reason']);
        Notification::assertNothingSent();

        Carbon::setTestNow(Carbon::parse('2026-08-17 08:05:00', 'Asia/Tehran'));
        $this->assertSame(1, $delivery->deliverGroup($group)['sent']);
        Notification::assertSentTo($manager, GenericNotification::class);

        Carbon::setTestNow(Carbon::parse('2026-08-17 14:00:00', 'Asia/Tehran'));
        $this->assertSame('daily_not_due', $delivery->deliverGroup($group)['reason']);

        Carbon::setTestNow(Carbon::parse('2026-08-18 08:05:00', 'Asia/Tehran'));
        app(NajmHodaGroupAttentionEvaluatorService::class)->evaluateGroup($group);
        $this->assertSame(1, $delivery->deliverGroup($group)['sent']);
    }

    private function member(Group $group, int $role, string $firstName, bool $isSystem): User
    {
        $user = User::create([
            'email' => uniqid('attention-delivery-', true) . '@example.test',
            'password' => Hash::make('password'), 'status' => 1,
            'first_name' => $firstName, 'last_name' => 'آزمایشی', 'is_system' => $isSystem,
        ]);
        GroupUser::create(['group_id' => $group->id, 'user_id' => $user->id, 'role' => $role, 'status' => 1]);
        return $user;
    }
}
