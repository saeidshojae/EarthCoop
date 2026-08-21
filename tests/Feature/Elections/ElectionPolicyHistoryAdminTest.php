<?php

namespace Tests\Feature\Elections;

use App\Models\ElectionPolicyVersion;
use App\Models\ElectionResponsibilityContractVersion;
use App\Models\GroupSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionPolicyHistoryAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_read_only_current_and_future_policy_history(): void
    {
        $this->withoutMiddleware();
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
        ]);

        $managerV1 = ElectionResponsibilityContractVersion::create([
            'position' => 'manager',
            'version' => 1,
            'body' => 'manager contract v1',
            'is_active' => true,
            'published_at' => now()->subDays(3),
        ]);
        $inspectorV1 = ElectionResponsibilityContractVersion::create([
            'position' => 'inspector',
            'version' => 1,
            'body' => 'inspector contract v1',
            'is_active' => true,
            'published_at' => now()->subDays(3),
        ]);

        $current = ElectionPolicyVersion::create([
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
            'manager_contract_version_id' => $managerV1->id,
            'inspector_contract_version_id' => $inspectorV1->id,
            'effective_at' => now()->subDay(),
            'change_reason' => 'current policy',
        ]);

        $future = ElectionPolicyVersion::create([
            'group_setting_id' => $setting->id,
            'level_key' => 'global',
            'version' => 2,
            'election_status' => true,
            'manager_count' => 9,
            'inspector_count' => 4,
            'voting_duration_days' => 12,
            'start_threshold' => 30,
            'cycle_interval_months' => 4,
            'response_duration_days' => 11,
            'manager_contract_version_id' => $managerV1->id,
            'inspector_contract_version_id' => $inspectorV1->id,
            'effective_at' => now()->addDays(5),
            'change_reason' => 'future policy',
        ]);

        $response = $this->get(route('admin.group.setting.index', ['history' => $setting->id]));

        $response->assertOk();
        $response->assertViewIs('admin.system-settings.elections.history');
        $response->assertViewHas('currentPolicy', fn ($policy) => $policy?->id === $current->id);
        $response->assertViewHas('futurePolicies', fn ($policies) => $policies->contains('id', $future->id));
        $response->assertSee('نسخه 1');
        $response->assertSee('future policy');
        $response->assertSee('v1');

        $this->assertDatabaseHas('election_policy_versions', [
            'id' => $current->id,
            'version' => 1,
            'change_reason' => 'current policy',
        ]);
        $this->assertDatabaseHas('election_policy_versions', [
            'id' => $future->id,
            'version' => 2,
            'change_reason' => 'future policy',
        ]);
    }
}
