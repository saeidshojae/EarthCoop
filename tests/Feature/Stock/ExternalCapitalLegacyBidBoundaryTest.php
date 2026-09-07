<?php

namespace Tests\Feature\Stock;

use App\Models\User;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Services\AuctionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ExternalCapitalLegacyBidBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_capital_primary_auction_cannot_enter_legacy_stock_wallet_bid_path(): void
    {
        $user = User::factory()->create();
        $stock = Stock::create([
            'issuer_type' => 'earthcoop',
            'startup_valuation' => 200_000,
            'startup_valuation_gol' => 20_000_000,
            'total_shares' => 20_000_000,
            'base_share_price' => 0.01,
            'base_share_price_gol' => 1,
            'available_shares' => 20_000,
        ]);

        $auction = Auction::create([
            'stock_id' => $stock->id,
            'market_type' => 'primary',
            'supply_source' => 'treasury',
            'settlement_channel' => 'external_capital',
            'quote_unit' => 'gol',
            'shares_count' => 2_000,
            'base_price' => 0.01,
            'base_price_gol' => 1,
            'start_time' => now()->subMinute(),
            'end_time' => now()->addDay(),
            'ends_at' => now()->addDay(),
            'status' => 'running',
            'type' => 'uniform_price',
            'settlement_mode' => 'manual',
            'lot_size' => 100,
        ]);

        try {
            app(AuctionService::class)->validateAndPlaceBid($user->id, $auction, 1.0, 1);
            $this->fail('External-capital auction reached the legacy Stock Wallet bid path.');
        } catch (RuntimeException $e) {
            $this->assertSame(
                'Legacy bid placement is disabled for canonical Gol auctions. Use canonical Stock services.',
                $e->getMessage()
            );
        }

        $this->assertDatabaseCount('wallets', 0);
        $this->assertDatabaseCount('bids', 0);
    }
}
