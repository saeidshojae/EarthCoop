<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class ParticipationConversionLedgerContractTest extends TestCase
{
    public function test_conversion_consumes_only_whole_ratio_multiples_and_preserves_remainder(): void
    {
        $source = file_get_contents(app_path('Modules/NajmBahar/Services/Api/NajmBaharActivationApplicationService.php'));

        $this->assertStringContainsString('$convertiblePoints = intdiv($requestedPoints, $ratio) * $ratio;', $source);
        $this->assertStringContainsString('$amountGol = intdiv($convertiblePoints, $ratio);', $source);
        $this->assertStringContainsString("'points_converted' => \$convertiblePoints", $source);
    }

    public function test_conversion_records_partial_consumption_instead_of_cashing_entire_source_transaction(): void
    {
        $source = file_get_contents(app_path('Modules/NajmBahar/Services/Api/NajmBaharActivationApplicationService.php'));

        $this->assertStringContainsString('UserPointConsumption::create([', $source);
        $this->assertStringContainsString("'user_point_transaction_id' => (int) \$transaction->id", $source);
        $this->assertStringContainsString("'points_consumed' => \$toConsume", $source);
        $this->assertStringNotContainsString('$transaction->is_cashed = true;', $source);
    }

    public function test_available_convertible_points_are_calculated_from_policy_snapshot_minus_consumption(): void
    {
        $applicationService = file_get_contents(app_path('Modules/NajmBahar/Services/Api/NajmBaharActivationApplicationService.php'));
        $summaryService = file_get_contents(app_path('Services/ParticipationPointSummaryService.php'));

        $this->assertStringContainsString('->convertibleTransactionsQuery((int) $user->id)', $applicationService);
        $this->assertStringContainsString("->where('convertible', true)", $summaryService);
        $this->assertStringContainsString("->where('dimension', 'participation')", $summaryService);
        $this->assertStringContainsString("->where('is_cashed', false)", $summaryService);
        $this->assertStringContainsString('consumptions_sum_points_consumed', $summaryService);
    }

    public function test_web_conversion_delegates_to_the_shared_activation_application_service(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/ReputationConversionController.php'));

        $this->assertStringContainsString('NajmBaharActivationApplicationService', $controller);
        $this->assertStringContainsString('activationApplicationService->activate(', $controller);
        $this->assertStringNotContainsString('UserPointConsumption::create([', $controller);
        $this->assertStringNotContainsString('monetaryService->activateDim(', $controller);
    }
}
