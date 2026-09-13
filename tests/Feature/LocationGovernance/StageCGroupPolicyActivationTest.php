<?php

namespace Tests\Feature\LocationGovernance;

use App\Enums\Membership\GroupCreationMode;
use App\Models\GroupCreationPolicy;
use Database\Seeders\LocationGovernanceBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StageCGroupPolicyActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_policies_are_ready_for_automatic_membership_when_group_cutover_is_enabled(): void
    {
        $this->seed(LocationGovernanceBootstrapSeeder::class);

        $policies = GroupCreationPolicy::query()
            ->with('dimension')
            ->where('enabled', true)
            ->whereNull('governance_area_id')
            ->get()
            ->keyBy(fn (GroupCreationPolicy $policy): string => (string) $policy->dimension?->key);

        $this->assertSame(
            ['age', 'gender', 'profession', 'public', 'specialty'],
            $policies->keys()->sort()->values()->all(),
        );

        foreach (['public', 'profession', 'specialty', 'age', 'gender'] as $dimensionKey) {
            $this->assertSame(
                GroupCreationMode::Automatic,
                $policies->get($dimensionKey)?->mode,
                "{$dimensionKey} must be automatic before Stage C canonical group cutover is considered ready.",
            );
        }
    }
}
