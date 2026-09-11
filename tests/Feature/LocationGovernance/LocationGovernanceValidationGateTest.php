<?php

namespace Tests\Feature\LocationGovernance;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LocationGovernanceValidationGateTest extends TestCase
{
    #[Test]
    public function full_validation_has_a_blocking_focused_location_governance_gate(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/integration-full-validation.yml'));

        $this->assertStringContainsString('Regression — Location / Governance', $workflow);
        $this->assertStringContainsString('id: regression_location_governance', $workflow);
        $this->assertStringContainsString(
            'tests/Unit/LocationGovernance tests/Unit/Membership tests/Feature/LocationGovernance',
            $workflow
        );
        $this->assertStringContainsString(
            'LOCATION_GOVERNANCE: ${{ steps.regression_location_governance.outcome }}',
            $workflow
        );
        $this->assertStringContainsString("'Location / Governance' \"$LOCATION_GOVERNANCE\"", $workflow);
        $this->assertStringContainsString('"$LOCATION_GOVERNANCE"', $workflow);
    }
}
