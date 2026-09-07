<?php

namespace Tests\Feature\Elections;

use App\Http\Middleware\AdminMiddleware;
use App\Models\ElectionPolicyVersion;
use App\Models\GroupSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionReportingPolicyAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_publish_reporting_privacy_policy(): void
    {
        // Keep Laravel's normal web middleware (especially SubstituteBindings,
        // session and shared validation errors) active. Only bypass the admin
        // authorization wrapper so this test exercises the real bound route.
        $this->withoutMiddleware(AdminMiddleware::class);
        $this->withoutVite();
        $admin = User::factory()->create();
        $this->actingAs($admin);

        $setting = GroupSetting::create([
            'level' => 'global',
            'manager_count' => 7,
            'inspector_count' => 3,
            'election_time' => 10,
            'max_for_election' => 20,
            'election_status' => 1,
            'second_election_time' => 3,
            'election_report_min_distinct_voters' => 10,
            'election_report_bucket_days' => 7,
            'election_meaningful_trend_min_net_change' => 3,
        ]);

        ElectionPolicyVersion::create([
            'group_setting_id' => $setting->id,
            'level_key' => 'global',
            'version' => 1,
            'election_status' => true,
            'manager_count' => 7,
            'inspector_count' => 3,
            'voting_duration_days' => 10,
            'start_threshold' => 20,
            'cycle_interval_months' => 3,
            'response_duration_days' => 7,
            'report_min_distinct_voters' => 10,
            'report_bucket_days' => 7,
            'meaningful_trend_min_net_change' => 3,
            'effective_at' => now()->subHour(),
            'change_reason' => 'baseline reporting privacy',
        ]);

        $this->get(route('admin.group.setting.index', ['reporting' => $setting->id]))
            ->assertOk()
            ->assertViewIs('admin.system-settings.elections.reporting')
            ->assertSee('حداقل رأی‌دهنده متمایز')
            ->assertSee('بازه تجمیع گزارش')
            ->assertSee('پیش‌فرض E0');

        $this->put(route('admin.group.setting.update', $setting), [
            'manager_count' => 7,
            'inspector_count' => 3,
            'election_time' => 10,
            'max_for_election' => 20,
            'second_election_time' => 3,
            'response_duration_days' => 7,
            'election_report_min_distinct_voters' => 12,
            'election_report_bucket_days' => 14,
            'election_meaningful_trend_min_net_change' => 5,
            'change_reason' => 'raise privacy floor',
        ])->assertRedirect();

        $latest = ElectionPolicyVersion::query()
            ->where('group_setting_id', $setting->id)
            ->orderByDesc('version')
            ->firstOrFail();

        $this->assertSame(2, (int) $latest->version);
        $this->assertSame(12, (int) $latest->report_min_distinct_voters);
        $this->assertSame(14, (int) $latest->report_bucket_days);
        $this->assertSame(5, (int) $latest->meaningful_trend_min_net_change);
        $this->assertSame('raise privacy floor', $latest->change_reason);
    }
}
