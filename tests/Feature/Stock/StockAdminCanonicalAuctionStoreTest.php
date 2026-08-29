<?php

namespace Tests\Feature\Stock;

use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAdminCanonicalAuctionStoreTest extends TestCase
{
    use RefreshDatabase;

    private function stock(): Stock
    {
        return Stock::create([
            'issuer_type' => 'earthcoop',
            'startup_valuation' => 12_000_000,
            'startup_valuation_gol' => 1_200_000_000,
            'total_shares' => 100_000_000,
            'base_share_price' => 0.12,
            'base_share_price_gol' => 12,
            'available_shares' => 10_000_000,
        ]);
    }

    public function test_admin_stores_primary_treasury_auction_with_integer_gol_pricing(): void
    {
        $this->withoutMiddleware();
        $stock = $this->stock();

        $response = $this->post('/admin/auctions', [
            'stock_id' => $stock->id,
            'shares_count' => 1_000_000,
            'base_price_gol' => 12,
            'market_type' => 'primary',
            'supply_source' => 'treasury',
            'quote_unit' => 'gol',
            'settlement_channel' => 'external_capital',
            'start_time' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'type' => 'uniform_price',
            'settlement_mode' => 'manual',
            'lot_size' => 100,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.auction.index'));

        $auction = Auction::query()->firstOrFail();
        $this->assertSame(12, (int) $auction->base_price_gol);
        $this->assertSame('gol', $auction->quote_unit);
        $this->assertSame('primary', $auction->market_type);
        $this->assertSame('treasury', $auction->supply_source);
        $this->assertSame('external_capital', $auction->settlement_channel);
        $this->assertSame('scheduled', $auction->status);
    }

    public function test_admin_cannot_create_primary_offering_beyond_ten_percent_envelope(): void
    {
        $this->withoutMiddleware();
        $stock = $this->stock();

        $response = $this->from('/admin/auctions/create')->post('/admin/auctions', [
            'stock_id' => $stock->id,
            'shares_count' => 10_000_001,
            'base_price_gol' => 12,
            'market_type' => 'primary',
            'supply_source' => 'treasury',
            'quote_unit' => 'gol',
            'settlement_channel' => 'external_capital',
            'start_time' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'type' => 'uniform_price',
            'settlement_mode' => 'manual',
            'lot_size' => 100,
        ]);

        $response->assertRedirect('/admin/auctions/create');
        $response->assertSessionHasErrors('shares_count');
        $this->assertDatabaseCount('auctions', 0);
    }
}
