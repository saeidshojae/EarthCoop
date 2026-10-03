<?php

namespace Tests\Feature\Temporal;

use App\Modules\Stock\Controllers\CanonicalAdminAuctionController;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Settlement\SettlementChannel;
use App\Temporal\Context\TemporalContextResolver;
use App\Temporal\Contracts\TemporalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class StockAdminAuctionIndexTemporalTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_auction_index_route_uses_temporal_safe_canonical_controller(): void
    {
        $route = Route::getRoutes()->match(Request::create('/admin/auctions', 'GET'));

        $this->assertSame(
            CanonicalAdminAuctionController::class.'@index',
            $route->getActionName(),
        );
    }

    public function test_persian_admin_auction_day_filter_uses_jalali_local_day_boundaries(): void
    {
        [$inside, $outside] = $this->seedAuctionsAroundCanonicalDay();
        app()->setLocale('fa');

        $context = app(TemporalContextResolver::class)->forLocale('fa');
        $localizedDay = app(TemporalService::class)->date('2026-10-01', $context, 'short');

        $view = app(CanonicalAdminAuctionController::class)->index(Request::create('/admin/auctions', 'GET', [
            'date_from' => $localizedDay,
            'date_to' => $localizedDay,
        ]));

        $ids = $view->getData()['auctions']->getCollection()->pluck('id')->all();

        $this->assertSame([$inside->id], $ids);
        $this->assertNotContains($outside->id, $ids);
    }

    public function test_english_admin_auction_day_filter_accepts_canonical_gregorian_date(): void
    {
        [$inside, $outside] = $this->seedAuctionsAroundCanonicalDay();
        app()->setLocale('en');

        $view = app(CanonicalAdminAuctionController::class)->index(Request::create('/admin/auctions', 'GET', [
            'date_from' => '2026-10-01',
            'date_to' => '2026-10-01',
        ]));

        $ids = $view->getData()['auctions']->getCollection()->pluck('id')->all();

        $this->assertSame([$inside->id], $ids);
        $this->assertNotContains($outside->id, $ids);
    }

    /** @return array{0: Auction, 1: Auction} */
    private function seedAuctionsAroundCanonicalDay(): array
    {
        $stock = Stock::create([
            'issuer_type' => 'earthcoop',
            'startup_valuation' => 12_000_000,
            'startup_valuation_gol' => 1_200_000_000,
            'total_shares' => 100_000_000,
            'base_share_price' => 0.12,
            'base_share_price_gol' => 12,
            'available_shares' => 10_000_000,
        ]);

        $common = [
            'stock_id' => $stock->id,
            'market_type' => 'primary',
            'supply_source' => 'treasury',
            'settlement_channel' => SettlementChannel::ACTIVE_BAHAR,
            'quote_unit' => 'gol',
            'shares_count' => 1000,
            'base_price' => 0.12,
            'base_price_gol' => 12,
            'status' => 'scheduled',
            'type' => 'uniform_price',
            'settlement_mode' => 'manual',
            'lot_size' => 1,
        ];

        $inside = Auction::create($common + [
            'start_time' => '2026-10-01 12:00:00',
            'end_time' => '2026-10-01 13:00:00',
            'ends_at' => '2026-10-01 13:00:00',
            'info' => 'inside temporal day',
        ]);

        $outside = Auction::create($common + [
            'start_time' => '2026-10-02 12:00:00',
            'end_time' => '2026-10-02 13:00:00',
            'ends_at' => '2026-10-02 13:00:00',
            'info' => 'outside temporal day',
        ]);

        return [$inside, $outside];
    }
}
