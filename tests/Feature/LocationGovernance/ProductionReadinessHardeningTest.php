<?php

namespace Tests\Feature\LocationGovernance;

use Database\Seeders\LocationGovernanceBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductionReadinessHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_requires_pending_residence_intents_migration(): void
    {
        $this->seed(LocationGovernanceBootstrapSeeder::class);

        DB::table('migrations')
            ->where('migration', '2026_09_13_000001_create_pending_residence_intents_table')
            ->delete();

        $this->setReleaseEvidence();

        $this->artisan('location-governance:readiness')
            ->expectsOutputToContain('[FAIL] required migrations')
            ->assertExitCode(1);
    }

    public function test_readiness_fails_when_reviewed_reference_governance_topology_is_not_applied(): void
    {
        $this->seed(LocationGovernanceBootstrapSeeder::class);
        $this->setReleaseEvidence();

        $this->artisan('location-governance:readiness')
            ->expectsOutputToContain('[FAIL] governance topology')
            ->assertExitCode(1);
    }

    public function test_readiness_fails_until_stage_c_group_policies_are_activated(): void
    {
        $this->seed(LocationGovernanceBootstrapSeeder::class);
        $this->setReleaseEvidence();

        $this->artisan('location-governance:readiness')
            ->expectsOutputToContain('[FAIL] Stage C group policies')
            ->assertExitCode(1);
    }

    private function setReleaseEvidence(): void
    {
        config()->set('location-governance.validation_sha', 'c26f012e9b683b69342050f435931486a3bb6874');
        config()->set('location-governance.uat_evidence', 'Full Validation #2615 / run 34828372298');
    }
}
