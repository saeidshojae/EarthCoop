<?php

namespace Tests\Feature\Communication;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Models\Election;
use App\Models\Group;
use App\Models\Poll;
use App\Models\User;
use App\Services\Communication\Context\WeeklyMemberReportContextBuilder;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class WeeklyMemberReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_context_contains_only_current_personal_activity_counts(): void
    {
        $user = User::factory()->create([
            'first_name' => 'سعید',
            'last_name' => 'آزمایشی',
            'is_system' => false,
        ]);

        $activeGroup = Group::query()->create(['name' => 'گروه فعال']);
        $expiredGroup = Group::query()->create(['name' => 'گروه منقضی']);
        $otherGroup = Group::query()->create(['name' => 'گروه دیگر']);

        DB::table('group_user')->insert([
            [
                'group_id' => $activeGroup->id,
                'user_id' => $user->id,
                'role' => 1,
                'status' => 1,
                'expired' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group_id' => $expiredGroup->id,
                'user_id' => $user->id,
                'role' => 1,
                'status' => 1,
                'expired' => now()->subDay(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Election::query()->create([
            'group_id' => $activeGroup->id,
            'cycle_number' => 1,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_closed' => false,
            'lifecycle_status' => ElectionLifecycleStatus::Open,
        ]);
        Election::query()->create([
            'group_id' => $activeGroup->id,
            'cycle_number' => 2,
            'starts_at' => now()->subWeek(),
            'ends_at' => now()->subDay(),
            'is_closed' => true,
            'lifecycle_status' => ElectionLifecycleStatus::Closed,
        ]);
        Election::query()->create([
            'group_id' => $otherGroup->id,
            'cycle_number' => 1,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_closed' => false,
            'lifecycle_status' => ElectionLifecycleStatus::Open,
        ]);

        Poll::query()->create([
            'group_id' => $activeGroup->id,
            'question' => 'نظرسنجی باز',
            'is_active' => true,
            'expires_at' => now()->addDay(),
            'created_by' => $user->id,
        ]);
        Poll::query()->create([
            'group_id' => $activeGroup->id,
            'question' => 'نظرسنجی منقضی',
            'is_active' => true,
            'expires_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);
        Poll::query()->create([
            'group_id' => $otherGroup->id,
            'question' => 'نظرسنجی دیگر',
            'is_active' => true,
            'expires_at' => now()->addDay(),
            'created_by' => $user->id,
        ]);

        DB::table('notifications')->insert([
            [
                'id' => (string) Str::uuid(),
                'type' => self::class,
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode(['message' => 'unread']),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'type' => self::class,
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode(['message' => 'read']),
                'read_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $period = CarbonPeriod::create('2026-09-22', '2026-09-28');
        $context = app(WeeklyMemberReportContextBuilder::class)->build($user, $period);

        $this->assertSame([
            'period_start',
            'period_end',
            'display_name',
            'groups_count',
            'open_elections_count',
            'open_polls_count',
            'unread_notifications_count',
        ], array_keys($context));
        $this->assertSame('2026-09-22', $context['period_start']);
        $this->assertSame('2026-09-28', $context['period_end']);
        $this->assertSame('سعید آزمایشی', $context['display_name']);
        $this->assertSame(1, $context['groups_count']);
        $this->assertSame(1, $context['open_elections_count']);
        $this->assertSame(1, $context['open_polls_count']);
        $this->assertSame(1, $context['unread_notifications_count']);
    }

    public function test_member_context_is_zero_safe_for_user_without_activity(): void
    {
        $user = User::factory()->create(['is_system' => false]);

        $context = app(WeeklyMemberReportContextBuilder::class)->build(
            $user,
            CarbonPeriod::create('2026-09-22', '2026-09-28'),
        );

        $this->assertSame(0, $context['groups_count']);
        $this->assertSame(0, $context['open_elections_count']);
        $this->assertSame(0, $context['open_polls_count']);
        $this->assertSame(0, $context['unread_notifications_count']);
    }
}
