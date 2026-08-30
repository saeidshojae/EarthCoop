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
        $this->assertStringContainsString("'external_irr'", $source);
        $this->assertStringContainsString("'external_usd'", $source);
    }

    public function test_external_auction_view_state_owns_currency_scoped_readiness(): void
    {
        $path = base_path('app/Modules/Stock/ExternalCapital/Services/ExternalCapitalAuctionViewState.php');

        $this->assertFileExists($path);
        $source = file_get_contents($path);

        $this->assertIsString($source);
        $this->assertStringContainsString('ExternalCapitalReadinessGate', $source);
        $this->assertStringContainsString('assertReadyForCurrency', $source);
        $this->assertStringContainsString("'IRR'", $source);
        $this->assertStringContainsString("'USD'", $source);
        $this->assertStringContainsString('externalCheckoutReady', $source);
    }

    public function test_stock_provider_composes_external_checkout_state_into_auction_view(): void
    {
        $source = file_get_contents(base_path('app/Providers/StockExternalCapitalServiceProvider.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString("View::composer('Stock::auction_show'", $source);
        $this->assertStringContainsString('ExternalCapitalAuctionViewState', $source);
    }
}
