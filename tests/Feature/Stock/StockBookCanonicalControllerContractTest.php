<?php

namespace Tests\Feature\Stock;

use Tests\TestCase;

class StockBookCanonicalControllerContractTest extends TestCase
{
    public function test_canonical_stock_book_does_not_touch_money_wallet_or_legacy_market_recalculation(): void
    {
        $source = file_get_contents(base_path('app/Modules/Stock/Controllers/CanonicalStockBookController.php'));

        $this->assertIsString($source);
        $this->assertStringNotContainsString('WalletService', $source);
        $this->assertStringNotContainsString('getOrCreateWallet', $source);
        $this->assertStringNotContainsString('recalculateMarketData', $source);
        $this->assertStringContainsString("whereIn('status', ['scheduled', 'running'])", $source);
        $this->assertStringContainsString("where('ends_at', '>', now())", $source);
        $this->assertStringContainsString("view('Stock::stock_dashboard'", $source);
    }
}
