<?php

namespace Tests\Feature\Elections;

use App\Models\ElectionResponsibilityContractVersion;
use App\Models\GroupSetting;
use App\Services\Elections\ElectionPolicyVersionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionPolicyEffectiveDatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_future_policy_does_not_change_legacy_mirror_until_effective_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-22 10:00:00'));

        foreach (['manager', 'inspector'] as $position) {
            ElectionResponsibilityContractVersion::create([
                'position' => $position,
                'version' => 1,
                'body' => $position.' contract',
                'is_active' => true,
                'published_at' => now()->subDay(),
            ]);
        }

        $setting = GroupSetting::create([
            'level' => 'global',
            'manager_count' => 7,
            'inspector_count' => 3,
            'election_time' => 10,
            'max_for_election' => 20,
            'election_status' => 1,
            'second_election_time' => 3,
        ]);

        $service = app(ElectionPolicyVersionService::class);
        $service->publishFromSetting($setting, null, 'baseline', now(), 7);

        $future = $service->publishSnapshot($setting, [
            'election_status' => true,
            'manager_count' => 9,
            'inspector_count' => 4,
            'voting_duration_days' => 12,
            'start_threshold' => 30,
            'cycle_interval_months' => 4,
            'response_duration_days' => 11,
        ], null, 'scheduled policy', now()->addDays(2));

        $setting->refresh();
        $this->assertSame(7, (int) $setting->manager_count);
        $this->assertSame(20, (int) $setting->max_for_election);
        $this->assertSame(3, (int) $setting->second_election_time);
        $this->assertTrue($future->effective_at->isFuture());
        $this->assertSame(0, $service->syncEffectiveMirrors());

        Carbon::setTestNow(Carbon::parse('2026-08-24 10:01:00'));
        $this->assertSame(1, $service->syncEffectiveMirrors());

        $setting->refresh();
        $this->assertSame(9, (int) $setting->manager_count);
        $this->assertSame(4, (int) $setting->inspector_count);
        $this->assertSame(12, (int) $setting->election_time);
        $this->assertSame(30, (int) $setting->max_for_election);
        $this->assertSame(4, (int) $setting->second_election_time);

        Carbon::setTestNow();
    }
}
