<?php

namespace Tests\Feature\LocationGovernance;

use App\Enums\Membership\GroupCreationMode;
use App\Models\GovernanceCapabilityPolicy;
use App\Models\GroupCreationPolicy;
use Database\Seeders\LocationGovernanceBootstrapSeeder;
use Database\Seeders\StageCCanonicalGroupPolicySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StageCGroupPolicyActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stage_c_transition_promotes_bootstrap_policies_to_automatic_membership(): void
    {
        $this->seed(LocationGovernanceBootstrapSeeder::class);

        foreach ($this->defaultPolicies() as $dimensionKey => $policy) {
            $this->assertSame(
                GroupCreationMode::OnDemand,
                $policy->mode,
                "{$dimensionKey} bootstrap policy must remain conservative before Stage C.",
            );
        }

        $this->seed(StageCCanonicalGroupPolicySeeder::class);

        foreach ($this->defaultPolicies() as $dimensionKey => $policy) {
            $this->assertSame(
                GroupCreationMode::Automatic,
                $policy->mode,
                "{$dimensionKey} must be automatic after the explicit Stage C policy transition.",
            );
        }

        $this->assertSame(
            'automatic',
            GovernanceCapabilityPolicy::query()
                ->where('scope', 'default')
                ->firstOrFail()
                ->capabilities['group_creation_mode'] ?? null,
        );
    }

    /** @return \Illuminate\Support\Collection<string, GroupCreationPolicy> */
    private function defaultPolicies()
    {
        $policies = GroupCreationPolicy::query()
            ->with('dimension')
            ->where('enabled', true)
            ->whereNull('governance_area_id')
            ->whereNull('governance_type')
            ->whereNull('governance_rank')
            ->where('priority', 0)
            ->get()
            ->keyBy(fn (GroupCreationPolicy $policy): string => (string) $policy->dimension?->key);

        $this->assertSame(
            ['age', 'gender', 'profession', 'public', 'specialty'],
            $policies->keys()->sort()->values()->all(),
        );

        return $policies;
    }
}
