<?php

namespace Tests\Feature\Communication;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Models\Election;
use App\Models\Group;
use App\Models\Poll;
use App\Models\User;
use App\Services\Communication\Context\WeeklyManagerReportContextBuilder;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class WeeklyManagerReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_context_includes_personal_member_context_and_only_current_managed_groups(): void
    {
        $user = User::factory()->create([
            'first_name' => 'مدیر',
            'last_name' => 'آزمایشی',
            'is_system' => false,
        ]);

        $managed = Group::query()->create(['name' => 'گروه مدیریت‌شده']);
        $memberOnly = Group::query()->create(['name' => 'گروه عضویت شخصی']);
        $expiredManaged = Group::query()->create(['name' => 'مدیریت منقضی']);

        DB::table('group_user')->insert([
            [
                'group_id' => $managed->id,
                'user_id' => $user->id,
                'role' => 3,
                'status' => 1,
                'expired' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group_id' => $memberOnly->id,
                'user_id' => $user->id,
                'role' => 1,
                'status' => 1,
                'expired' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'group_id' => $expiredManaged->id,
                'user_id' => $user->id,
                'role' => 3,
                'status' => 1,
                'expired' => now()->subDay(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Election::query()->create([
            'group_id' => $managed->id,
            'cycle_number' => 1,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_closed' => false,
            'lifecycle_status' => ElectionLifecycleStatus::Open,
        ]);
        Election::query()->create([
            'group_id' => $memberOnly->id,
            'cycle_number' => 1,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_closed' => false,
            'lifecycle_status' => ElectionLifecycleStatus::Open,
        ]);

        Poll::query()->create([
            'group_id' => $managed->id,
            'question' => 'مدیریتی',
            'is_active' => true,
            'expires_at' => now()->addDay(),
            'created_by' => $user->id,
        ]);
        Poll::query()->create([
            'group_id' => $memberOnly->id,
            'question' => 'شخصی',
            'is_active' => true,
            'expires_at' => now()->addDay(),
            'created_by' => $user->id,
        ]);

        $context = app(WeeklyManagerReportContextBuilder::class)->build(
            $user,
            CarbonPeriod::create('2026-09-22', '2026-09-28'),
        );

        $this->assertSame(2, $context['groups_count']);
        $this->assertSame(2, $context['open_elections_count']);
        $this->assertSame(2, $context['open_polls_count']);
        $this->assertSame(1, $context['managed_groups_count']);
        $this->assertSame([$managed->id], $context['managed_group_ids']);
        $this->assertSame(1, $context['open_elections_in_managed_groups_count']);
        $this->assertSame(1, $context['open_polls_in_managed_groups_count']);
    }
}
