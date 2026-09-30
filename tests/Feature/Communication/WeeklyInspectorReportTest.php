<?php

namespace Tests\Feature\Communication;

use App\Enums\Elections\ElectionLifecycleStatus;
use App\Models\Election;
use App\Models\Group;
use App\Models\Poll;
use App\Models\User;
use App\Services\Communication\Context\WeeklyInspectorReportContextBuilder;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class WeeklyInspectorReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_inspector_context_includes_personal_member_context_and_only_current_inspected_groups(): void
    {
        $user = User::factory()->create([
            'first_name' => 'بازرس',
            'last_name' => 'آزمایشی',
            'is_system' => false,
        ]);

        $inspected = Group::query()->create(['name' => 'گروه بازرسی‌شده']);
        $memberOnly = Group::query()->create(['name' => 'گروه عضویت شخصی']);
        $inactiveInspected = Group::query()->create(['name' => 'بازرسی غیرفعال']);

        DB::table('group_user')->insert([
            [
                'group_id' => $inspected->id,
                'user_id' => $user->id,
                'role' => 2,
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
                'group_id' => $inactiveInspected->id,
                'user_id' => $user->id,
                'role' => 2,
                'status' => 0,
                'expired' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Election::query()->create([
            'group_id' => $inspected->id,
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
            'group_id' => $inspected->id,
            'question' => 'بازرسی',
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

        $context = app(WeeklyInspectorReportContextBuilder::class)->build(
            $user,
            CarbonPeriod::create('2026-09-22', '2026-09-28'),
        );

        $this->assertSame(2, $context['groups_count']);
        $this->assertSame(2, $context['open_elections_count']);
        $this->assertSame(2, $context['open_polls_count']);
        $this->assertSame(1, $context['inspected_groups_count']);
        $this->assertSame([$inspected->id], $context['inspected_group_ids']);
        $this->assertSame(1, $context['open_elections_in_inspected_groups_count']);
        $this->assertSame(1, $context['open_polls_in_inspected_groups_count']);
    }
}
