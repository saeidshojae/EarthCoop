<?php

namespace Tests\Feature\LocationGovernance;

use Tests\TestCase;

class LocationStructureClaimMigrationContractTest extends TestCase
{
    public function test_structural_claim_foreign_key_names_fit_mysql_identifier_limit(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_18_000002_create_location_structure_claims_tables.php'));

        $this->assertStringContainsString("'lsc_evidence_claim_fk'", $migration);
        $this->assertStringContainsString("'lsc_evidence_user_fk'", $migration);
    }

    public function test_threshold_setting_migration_is_additive_and_idempotent(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_19_000001_add_location_governance_thresholds_to_setting.php'));

        $this->assertStringContainsString("Schema::hasTable('setting')", $migration);
        $this->assertStringContainsString("Schema::hasColumn('setting', 'location_proposal_verification_threshold')", $migration);
        $this->assertStringContainsString("Schema::hasColumn('setting', 'location_structure_claim_verification_threshold')", $migration);
    }

}
