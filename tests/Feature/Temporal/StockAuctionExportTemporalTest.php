<?php

namespace Tests\Feature\Temporal;

use App\Modules\Stock\Controllers\AuctionController;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Settlement\SettlementChannel;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAuctionExportTemporalTest extends TestCase
{
    use RefreshDatabase;

    public function test_auction_export_uses_active_temporal_context_instead_of_forcing_jalali(): void
    {
        app()->setLocale('en');

        $stock = Stock::create([
            'issuer_type' => 'earthcoop',
            'startup_valuation' => 12_000_000,
            'startup_valuation_gol' => 1_200_000_000,
            'total_shares' => 100_000_000,
            'base_share_price' => 0.12,
            'base_share_price_gol' => 12,
            'available_shares' => 10_000_000,
        ]);

        $auction = Auction::create([
            'stock_id' => $stock->id,
            'market_type' => 'primary',
            'supply_source' => 'treasury',
            'settlement_channel' => SettlementChannel::ACTIVE_BAHAR,
            'quote_unit' => 'gol',
            'shares_count' => 1000,
            'base_price' => 0.12,
            'base_price_gol' => 12,
            'start_time' => '2026-10-01 12:00:00',
            'end_time' => '2026-10-01 13:00:00',
            'ends_at' => '2026-10-01 13:00:00',
            'status' => 'scheduled',
            'type' => 'uniform_price',
            'settlement_mode' => 'manual',
            'lot_size' => 1,
        ]);

        $response = app(AuctionController::class)->export($auction);

        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();

        $context = app(TemporalContextResolver::class)->forLocale('en');
        $expectedStart = app(TemporalService::class)->dateTime($auction->start_time, $context, 'short');
        $expectedEnd = app(TemporalService::class)->dateTime($auction->ends_at, $context, 'short');

        $this->assertStringContainsString($expectedStart, $csv);
        $this->assertStringContainsString($expectedEnd, $csv);
    }
}
