<?php

namespace Tests\Feature\Stock;

use Tests\TestCase;

class ExternalCapitalAuctionUiContractTest extends TestCase
{
    public function test_external_primary_auction_view_uses_server_readiness_and_canonical_checkout_fields(): void
    {
        $source = file_get_contents(base_path('app/Modules/Stock/Views/auction_show.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('$externalCheckoutReady', $source);
        $this->assertStringContainsString('external-checkout', $source);
        $this->assertStringContainsString('name="price_gol"', $source);
        $this->assertStringContainsString('name="quantity"', $source);
        $this->assertStringNotContainsString('name="amount_irr"', $source);
        $this->assertStringNotContainsString('name="currency"', $source);
        $this->assertStringContainsString('آمادگی تسویه خارجی', $source);
    }

    public function test_auction_show_controller_exposes_currency_scoped_external_checkout_readiness(): void
    {
        $source = file_get_contents(base_path('app/Modules/Stock/Controllers/AuctionController.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('ExternalCapitalReadinessGate', $source);
        $this->assertStringContainsString('assertReadyForCurrency', $source);
        $this->assertStringContainsString("'IRR'", $source);
        $this->assertStringContainsString('externalCheckoutReady', $source);
    }
}
