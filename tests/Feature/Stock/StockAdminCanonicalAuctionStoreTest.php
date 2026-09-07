<?php

namespace Tests\Feature\Stock;

use App\Modules\Stock\Controllers\CanonicalAdminAuctionController;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Settlement\SettlementChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StockAdminCanonicalAuctionStoreTest extends TestCase
{
    use RefreshDatabase;

    private function stock(array $overrides = []): Stock
    {
        return Stock::create(array_merge([
            'issuer_type' => 'earthcoop',
            'startup_valuation' => 12_000_000,
            'startup_valuation_gol' => 1_200_000_000,
            'total_shares' => 100_000_000,
            'base_share_price' => 0.12,
            'base_share_price_gol' => 12,
            'available_shares' => 10_000_000,
        ], $overrides));
    }

    private function payload(Stock $stock, array $overrides = []): array
    {
        return array_merge([
            'stock_id' => $stock->id,
            'shares_count' => 1_000_000,
            'base_price_gol' => 12,
            'settlement_channel' => SettlementChannel::EXTERNAL_IRR,
            'start_time' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'type' => 'uniform_price',
            'settlement_mode' => 'manual',
            'lot_size' => 100,
        ], $overrides);
    }

    public function test_runtime_admin_store_route_resolves_to_canonical_controller(): void
    {
        $route = Route::getRoutes()->match(Request::create('/admin/auctions', 'POST'));

        $this->assertSame(
            CanonicalAdminAuctionController::class . '@store',
            $route->getActionName()
        );
    }

    public function test_admin_stores_primary_treasury_auction_with_integer_gol_pricing_and_selected_settlement(): void
    {
        $this->withoutMiddleware();
        $stock = $this->stock();

        $response = $this->post('/admin/auctions', $this->payload($stock));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.auction.index'));

        $auction = Auction::query()->firstOrFail();
        $this->assertSame(12, (int) $auction->base_price_gol);
        $this->assertSame('gol', $auction->quote_unit);
        $this->assertSame('primary', $auction->market_type);
        $this->assertSame('treasury', $auction->supply_source);
        $this->assertSame(SettlementChannel::EXTERNAL_IRR, $auction->settlement_channel);
        $this->assertSame('scheduled', $auction->status);
    }

    public function test_admin_can_choose_active_bahar_settlement(): void
    {
        $this->withoutMiddleware();
        $stock = $this->stock();

        $response = $this->post('/admin/auctions', $this->payload($stock, [
            'shares_count' => 500_000,
            'settlement_channel' => SettlementChannel::ACTIVE_BAHAR,
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertSame(SettlementChannel::ACTIVE_BAHAR, Auction::query()->firstOrFail()->settlement_channel);
    }

    public function test_admin_rejects_external_usd_while_usd_rollout_is_closed(): void
    {
        $this->withoutMiddleware();
        $stock = $this->stock();

        $response = $this->from('/admin/auctions/create')->post('/admin/auctions', $this->payload($stock, [
            'shares_count' => 500_000,
            'settlement_channel' => SettlementChannel::EXTERNAL_USD,
        ]));

        $response->assertRedirect('/admin/auctions/create');
        $response->assertSessionHasErrors('settlement_channel');
        $this->assertDatabaseCount('auctions', 0);
    }

    public function test_admin_rejects_offering_larger_than_available_treasury_shares(): void
    {
        $this->withoutMiddleware();
        $stock = $this->stock();

        $response = $this->from('/admin/auctions/create')->post('/admin/auctions', $this->payload($stock, [
            'shares_count' => 10_000_001,
        ]));

        $response->assertRedirect('/admin/auctions/create');
        $response->assertSessionHasErrors([
            'shares_count' => 'EarthCoop offering exceeds the configured primary allocation cap.',
        ]);
        $this->assertDatabaseCount('auctions', 0);
    }

    public function test_admin_rejects_new_open_offering_when_cumulative_primary_envelope_would_exceed_ten_percent(): void
    {
        $this->withoutMiddleware();
        $stock = $this->stock(['available_shares' => 20_000_000]);

        Auction::create([
            'stock_id' => $stock->id,
            'market_type' => 'primary',
            'supply_source' => 'treasury',
            'settlement_channel' => SettlementChannel::EXTERNAL_IRR,
            'quote_unit' => 'gol',
            'shares_count' => 6_000_000,
            'base_price' => 0.12,
            'base_price_gol' => 12,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDays(2),
            'ends_at' => now()->addDays(2),
            'status' => 'scheduled',
            'type' => 'uniform_price',
            'settlement_mode' => 'manual',
            'lot_size' => 100,
        ]);

        $response = $this->from('/admin/auctions/create')->post('/admin/auctions', $this->payload($stock, [
            'shares_count' => 5_000_000,
        ]));

        $response->assertRedirect('/admin/auctions/create');
        $response->assertSessionHasErrors([
            'shares_count' => 'EarthCoop open offerings would oversubscribe the primary allocation cap.',
        ]);
        $this->assertDatabaseCount('auctions', 1);
    }
}
