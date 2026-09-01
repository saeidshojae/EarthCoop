<?php

namespace Tests\Feature\Reputation;

use Tests\TestCase;

class ParticipationSummaryBoundaryContractTest extends TestCase
{
    public function test_wallet_and_conversion_controller_share_the_canonical_participation_summary_boundary(): void
    {
        $walletController = file_get_contents(app_path('Http/Controllers/NajmBaharController.php'));
        $conversionController = file_get_contents(app_path('Http/Controllers/ReputationConversionController.php'));

        $this->assertStringContainsString('ParticipationPointSummaryService', $walletController);
        $this->assertStringContainsString('participationPointSummaryService->forUser', $walletController);

        $this->assertStringContainsString('ParticipationPointSummaryService', $conversionController);
        $this->assertStringContainsString('participationPointSummaryService->forUser', $conversionController);
        $this->assertStringContainsString('->convertibleTransactionsQuery($user->id)', $conversionController);
        $this->assertStringContainsString('->participationReversalPoints($user->id)', $conversionController);

        $this->assertStringNotContainsString('private function convertibleTransactions', $conversionController);
        $this->assertStringNotContainsString('private function participationReversalPoints', $conversionController);
        $this->assertStringNotContainsString('private function legacyCashedPoints', $conversionController);
    }
}
