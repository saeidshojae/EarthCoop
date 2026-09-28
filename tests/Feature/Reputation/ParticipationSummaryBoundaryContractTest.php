<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class ParticipationSummaryBoundaryContractTest extends TestCase
{
    public function test_wallet_web_info_and_activation_share_the_canonical_participation_summary_boundary(): void
    {
        $walletController = file_get_contents(app_path('Http/Controllers/NajmBaharController.php'));
        $conversionController = file_get_contents(app_path('Http/Controllers/ReputationConversionController.php'));
        $activationService = file_get_contents(app_path('Modules/NajmBahar/Services/Api/NajmBaharActivationApplicationService.php'));

        $this->assertStringContainsString('ParticipationPointSummaryService', $walletController);
        $this->assertStringContainsString('participationPointSummaryService->forUser', $walletController);

        $this->assertStringContainsString('ParticipationPointSummaryService', $conversionController);
        $this->assertStringContainsString('participationPointSummaryService->forUser', $conversionController);
        $this->assertStringContainsString('NajmBaharActivationApplicationService', $conversionController);

        $this->assertStringContainsString('ParticipationPointSummaryService', $activationService);
        $this->assertStringContainsString('->convertibleTransactionsQuery((int) $user->id)', $activationService);
        $this->assertStringContainsString('->participationReversalPoints((int) $user->id)', $activationService);

        $this->assertStringNotContainsString('private function convertibleTransactions', $conversionController);
        $this->assertStringNotContainsString('private function participationReversalPoints', $conversionController);
        $this->assertStringNotContainsString('private function legacyCashedPoints', $conversionController);
    }
}
