<?php

namespace Tests\Feature\LocationGovernance;

use Tests\TestCase;

class LocationStructureClaimReleaseContractTest extends TestCase
{
    public function test_structural_claim_migration_is_a_readiness_prerequisite(): void
    {
        $command = file_get_contents(app_path('Console/Commands/LocationGovernanceReadinessCommand.php'));

        $this->assertStringContainsString(
            "'2026_09_18_000002_create_location_structure_claims_tables'",
            $command
        );
    }
}
