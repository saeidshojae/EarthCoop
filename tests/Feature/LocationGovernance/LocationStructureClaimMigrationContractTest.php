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
}
