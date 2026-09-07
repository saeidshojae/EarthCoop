<?php

namespace Tests\Feature\NajmHoda;

use App\Services\NajmHoda\FounderOps\FounderActionOutcomeVerificationService;
use App\Services\NajmHoda\FounderOps\FounderExecutiveConnectivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FounderExecutiveAcceptanceScenariosTest extends TestCase
{
    use RefreshDatabase;

    public function test_executive_connectivity_has_no_ambiguous_missing_executable_actions(): void
    {
        $report = app(FounderExecutiveConnectivityService::class)->report();

        $this->assertSame(0, (int) data_get($report, 'summary.missing_executable_actions'));
    }

    public function test_secretariat_external_dispatch_remains_explicitly_blocked_without_real_transport(): void
    {
        $report = app(FounderExecutiveConnectivityService::class)->report();
        $dispatch = data_get($report, 'domains.secretariat.actions.dispatch_formal_record');

        $this->assertIsArray($dispatch);
        $this->assertSame('approval_required', $dispatch['mode']);
        $this->assertSame('blocked_dependency', $dispatch['state']);
        $this->assertSame('real_transport_not_available', data_get($dispatch, 'block.reason'));

        $verification = app(FounderActionOutcomeVerificationService::class)->verify(
            'secretariat',
            'dispatch_formal_record',
            ['dispatch_id' => 1],
            ['entity_type' => 'secretariat_record', 'entity_id' => 1]
        );

        $this->assertFalse((bool) $verification['verified']);
        $this->assertSame('not_configured', $verification['status']);
        $this->assertSame('no_canonical_outcome_verifier', $verification['reason']);
    }

    public function test_financial_outcomes_are_never_verified_without_persisted_evidence(): void
    {
        $verifier = app(FounderActionOutcomeVerificationService::class);

        $bahar = $verifier->verify(
            'najm_bahar',
            'execute_transaction',
            ['intent_id' => 999999, 'transaction_id' => 999999],
            ['entity_type' => 'founder_najm_bahar_transaction_intent', 'entity_id' => 999999]
        );
        $stock = $verifier->verify(
            'stock',
            'settle_auction',
            ['auction_id' => 999999, 'auction_status' => 'settled'],
            ['entity_type' => 'stock_auction', 'entity_id' => 999999]
        );

        $this->assertFalse((bool) $bahar['verified']);
        $this->assertSame('failed', $bahar['status']);
        $this->assertFalse((bool) data_get($bahar, 'evidence.transaction_persisted'));

        $this->assertFalse((bool) $stock['verified']);
        $this->assertSame('failed', $stock['status']);
        $this->assertNull(data_get($stock, 'evidence.persisted_status'));
    }
}
