<?php

namespace Tests\Feature\Elections;

use App\Models\Election;
use App\Models\ElectionPolicyVersion;
use App\Models\ElectionResponsibilityContractVersion;
use App\Models\Group;
use App\Models\GroupSetting;
use App\Models\GroupUser;
use App\Models\User;
use App\Services\Elections\ElectionCycleService;
use App\Services\Elections\ElectionPolicyResolver;
use App\Services\Elections\ElectionPolicyVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ElectionPolicyVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_cycle_freezes_effective_policy_and_later_admin_change_does_not_mutate_it(): void
    {
        $manifest = array_fill_keys(ElectionResponsibilityContractVersion::REQUIRED_CLAUSES, 'متن معتبر قرارداد تست policy');
        ElectionResponsibilityContractVersion::create([
            'position' => 'manager',
            'version' => 1,
            'body' => 'manager E0 policy fixture',
            'clause_manifest' => $manifest,
            'e0_compliant' => true,
            'is_active' => true,
            'published_at' => now()->subDay(),
        ]);

        $setting = GroupSetting::create([
            'level' => 'global',
            'manager_count' => 1,
            'inspector_count' => 0,
            'election_time' => 10,
            'max_for_election' => 1,
            'election_status' => 1,
            'second_election_time' => 0,
        ]);
        $group = Group::create([
            'name' => 'Policy freeze group',
            'group_type' => '0',
            'location_level' => 'global',
            'address_id' => null,
        ]);
        $user = User::factory()->create(['is_system' => false]);
        GroupUser::create(['group_id' => $group->id, 'user_id' => $user->id, 'role' => 1, 'status' => 1]);

        $cycleOne = app(ElectionCycleService::class)->ensureForGroup($group);
        $this->assertNotNull($cycleOne);
        $policyOne = app(ElectionPolicyResolver::class)->resolveForElection($cycleOne);
        $this->assertSame(1, $policyOne->manager_count);
        $this->assertSame(10, $policyOne->voting_duration_days);

        $setting->update(['manager_count' => 3, 'election_time' => 20]);
        $policyTwo = app(ElectionPolicyVersionService::class)->publishFromSetting(
            $setting, null, 'test_policy_change', now(), 9
        );

        $this->assertGreaterThan($policyOne->version, $policyTwo->version);
        $this->assertSame($policyOne->id, $cycleOne->refresh()->policy_version_id);
        $this->assertSame(1, app(ElectionPolicyResolver::class)->resolveForElection($cycleOne)->manager_count);

        // A cancelled cycle did not establish a term, so E9 permits immediate replacement.
        $cycleOne->forceFill(['lifecycle_status' => 'cancelled', 'is_closed' => true])->save();
        $cycleTwo = app(ElectionCycleService::class)->ensureForGroup($group);

        $this->assertNotNull($cycleTwo);
        $this->assertNotSame($cycleOne->id, $cycleTwo->id);
        $this->assertSame($policyTwo->id, $cycleTwo->policy_version_id);
        $this->assertSame(3, app(ElectionPolicyResolver::class)->resolveForElection($cycleTwo)->manager_count);
        $this->assertSame(20, app(ElectionPolicyResolver::class)->resolveForElection($cycleTwo)->voting_duration_days);
    }

    public function test_published_policy_payload_is_immutable(): void
    {
        $setting = GroupSetting::create([
            'level' => 'global', 'manager_count' => 1, 'inspector_count' => 0,
            'election_time' => 10, 'max_for_election' => 1, 'election_status' => 1,
            'second_election_time' => 3,
        ]);
        $policy = app(ElectionPolicyVersionService::class)->publishFromSetting($setting, null, 'immutable_test');

        $this->expectException(LogicException::class);
        $policy->manager_count = 99;
        $policy->save();
    }

    public function test_compatibility_setting_gets_a_versioned_baseline_on_first_use(): void
    {
        $setting = GroupSetting::create([
            'level' => 'global', 'manager_count' => 2, 'inspector_count' => 1,
            'election_time' => 8, 'max_for_election' => 2, 'election_status' => 1,
            'second_election_time' => 4,
        ]);
        $group = Group::create([
            'name' => 'Compatibility policy group', 'group_type' => '0',
            'location_level' => 'global', 'address_id' => null,
        ]);

        $policy = app(ElectionPolicyResolver::class)->resolveEffectiveForGroup($group);

        $this->assertSame($setting->id, $policy->group_setting_id);
        $this->assertSame(2, $policy->manager_count);
        $this->assertSame(1, ElectionPolicyVersion::where('group_setting_id', $setting->id)->count());
    }

    public function test_retired_contract_frozen_into_effective_policy_does_not_block_cycle_creation(): void
    {
        $manifest = array_fill_keys(ElectionResponsibilityContractVersion::REQUIRED_CLAUSES, 'متن معتبر قرارداد فریز شده');
        $frozenContract = ElectionResponsibilityContractVersion::create([
            'position' => 'manager',
            'version' => 1,
            'body' => 'frozen manager E0 contract',
            'clause_manifest' => $manifest,
            'e0_compliant' => true,
            'is_active' => false,
            'published_at' => now()->subWeek(),
        ]);

        $setting = GroupSetting::create([
            'level' => 'global',
            'manager_count' => 1,
            'inspector_count' => 0,
            'election_time' => 10,
            'max_for_election' => 1,
            'election_status' => 1,
            'second_election_time' => 3,
        ]);

        $policy = ElectionPolicyVersion::create([
            'group_setting_id' => $setting->id,
            'level_key' => 'global',
            'version' => 1,
            'election_status' => true,
            'manager_count' => 1,
            'inspector_count' => 0,
            'voting_duration_days' => 10,
            'start_threshold' => 1,
            'cycle_interval_months' => 3,
            'response_duration_days' => 7,
            'report_min_distinct_voters' => 10,
            'report_bucket_days' => 7,
            'meaningful_trend_min_net_change' => 3,
            'manager_contract_version_id' => $frozenContract->id,
            'inspector_contract_version_id' => null,
            'effective_at' => now()->subDay(),
            'change_reason' => 'historical frozen contract test',
        ]);

        $group = Group::create([
            'name' => 'Retired frozen contract group',
            'group_type' => '0',
            'location_level' => 'global',
            'address_id' => null,
        ]);
        $member = User::factory()->create(['is_system' => false]);
        GroupUser::create([
            'group_id' => $group->id,
            'user_id' => $member->id,
            'role' => 1,
            'status' => 1,
        ]);

        $election = app(ElectionCycleService::class)->ensureForGroup($group);

        $this->assertNotNull($election);
        $this->assertSame($policy->id, (int) $election->policy_version_id);
        $this->assertSame($frozenContract->id, (int) $policy->manager_contract_version_id);
    }
}
