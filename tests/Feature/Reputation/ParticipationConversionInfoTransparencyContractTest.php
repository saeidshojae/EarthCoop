<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class ParticipationConversionInfoTransparencyContractTest extends TestCase
{
    public function test_conversion_info_exposes_canonical_participation_breakdown_without_removing_legacy_fields(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ReputationConversionController.php'));

        foreach ([
            "'total_points' => \$totalPoints",
            "'uncashed_points' => \$uncashedPoints",
            "'cashed_points' => \$cashedPoints",
            "'convertible_awarded_points' => \$pointSummary['convertible_awarded_points']",
            "'ledger_consumed_points' => \$pointSummary['ledger_consumed_points']",
            "'legacy_cashed_points' => \$pointSummary['legacy_cashed_points']",
            "'participation_reversal_points' => \$pointSummary['participation_reversal_points']",
            "'remaining_convertible_points' => \$pointSummary['remaining_convertible_points']",
        ] as $contract) {
            $this->assertStringContainsString($contract, $controller);
        }
    }
}
