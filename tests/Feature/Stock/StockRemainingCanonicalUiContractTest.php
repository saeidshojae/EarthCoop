<?php

namespace Tests\Feature\Stock;

use App\Models\User;
use App\Modules\Stock\Controllers\AuctionController;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Settlement\SettlementChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class StockRemainingCanonicalUiContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function stock(): Stock
    {
        return Stock::create([
            'issuer_type' => 'earthcoop',
            'startup_valuation' => 200_000,
            'startup_valuation_gol' => 20_000_000,
            'total_shares' => 20_000_000,
            'base_share_price' => 0.01,
            'base_share_price_gol' => 1,
            'available_shares' => 20_000,
        ]);
    }

    private function auction(Stock $stock, array $overrides = []): Auction
    {
        return Auction::create(array_merge([
            'stock_id' => $stock->id,
            'market_type' => 'primary',
            'supply_source' => 'treasury',
            'settlement_channel' => SettlementChannel::EXTERNAL_IRR,
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
            'lot_size' => 1,
        ], $overrides))->load('stock');
    }

    public function test_admin_stock_dashboard_uses_only_canonical_gol_bahar_vocabulary(): void
    {
        $stock = $this->stock();

        $html = view('Stock::admin_stock_info', [
            'stock' => $stock,
            'alerts' => [],
            'stats' => null,
        ])->render();

        $this->assertStringContainsString('ارزش‌گذاری کل', $html);
        $this->assertStringContainsString('200,000 بهار', $html);
        $this->assertStringContainsString('قیمت پایه هر سهم', $html);
        $this->assertStringContainsString('1 گل', $html);
        $this->assertStringContainsString('0.01 بهار', $html);
        $this->assertStringNotContainsString('ارزش پایه استارتاپ', $html);
        $this->assertStringNotContainsString('ریال', $html);
    }

    public function test_public_primary_auction_show_does_not_fall_back_to_legacy_rial_price(): void
    {
        $auction = $this->auction($this->stock());

        $html = view('Stock::auction_show', [
            'auction' => $auction,
            'orderBook' => collect(),
            'userBids' => collect(),
        ])->render();

        $this->assertStringContainsString('1 گل', $html);
        $this->assertStringContainsString('0.01 بهار', $html);
        $this->assertStringContainsString('عرضه اولیه خزانه EarthCoop', $html);
        $this->assertStringContainsString('تسویه خارجی', $html);
        $this->assertStringContainsString('مبلغ ریالی/دلاری از قیمت گل و نرخ مرجع معتبر در سمت سرور محاسبه می‌شود', $html);
        $this->assertStringNotContainsString('قیمت پایه (ریال)', $html);
        $this->assertStringNotContainsString('قیمت پیشنهادی (ریال)', $html);
        $this->assertStringNotContainsString('قیمت هر سهم (ریال)', $html);
    }

    public function test_expired_running_auction_explains_why_bidding_is_unavailable(): void
    {
        $auction = $this->auction($this->stock(), [
            'end_time' => now()->subMinute(),
            'ends_at' => now()->subMinute(),
        ]);

        $html = view('Stock::auction_show', [
            'auction' => $auction,
            'orderBook' => collect(),
            'userBids' => collect(),
        ])->render();

        $this->assertStringContainsString('مهلت ثبت پیشنهاد پایان یافته است', $html);
        $this->assertStringContainsString('پایان‌یافته؛ در انتظار بستن و تسویه', $html);
        $this->assertStringNotContainsString('ثبت پیشنهاد خرید', $html);
    }

    public function test_admin_primary_offering_form_exposes_canonical_settlement_choice(): void
    {
        $stock = $this->stock();

        $html = view('Stock::admin_auction_create', [
            'stock' => $stock,
        ])->render();

        $this->assertStringContainsString('name="settlement_channel"', $html);
        $this->assertStringContainsString('value="active_bahar"', $html);
        $this->assertStringContainsString('value="external_irr"', $html);
        $this->assertStringContainsString('تسویه با بهار فعال', $html);
        $this->assertStringContainsString('تسویه خارجی با ریال', $html);
        $this->assertStringNotContainsString('value="external_capital"', $html);
        $this->assertStringNotContainsString('value="external_usd"', $html);
    }

    public function test_admin_store_persists_canonical_external_irr_offering_from_gol_input(): void
    {
        $stock = $this->stock();
        $request = Request::create('/admin/auctions', 'POST', [
            'stock_id' => $stock->id,
            'shares_count' => 500,
            'base_price_gol' => 1,
            'settlement_channel' => SettlementChannel::EXTERNAL_IRR,
            'start_time' => now()->addHour()->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'type' => 'uniform_price',
            'settlement_mode' => 'manual',
            'lot_size' => 1,
            'info' => 'UAT external offering',
        ]);
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);

        app(AuctionController::class)->adminStore($request);

        $this->assertDatabaseHas('auctions', [
            'stock_id' => $stock->id,
            'market_type' => 'primary',
            'supply_source' => 'treasury',
            'settlement_channel' => SettlementChannel::EXTERNAL_IRR,
            'quote_unit' => 'gol',
            'shares_count' => 500,
            'base_price_gol' => 1,
            'status' => 'scheduled',
        ]);
    }

    public function test_stock_book_does_not_present_expired_running_auction_as_active(): void
    {
        $stock = $this->stock();
        $expired = $this->auction($stock, [
            'shares_count' => 321,
            'end_time' => now()->subMinute(),
            'ends_at' => now()->subMinute(),
        ]);
        $active = $this->auction($stock, [
            'shares_count' => 654,
            'end_time' => now()->addDay(),
            'ends_at' => now()->addDay(),
        ]);

        $html = view('Stock::stock_dashboard', [
            'stock' => $stock,
            'auctions' => collect([$expired, $active]),
            'soldShares' => 0,
            'userHoldings' => collect(),
            'walletData' => null,
        ])->render();

        $this->assertStringNotContainsString(route('auction.show', $expired), $html);
        $this->assertStringContainsString(route('auction.show', $active), $html);
        $this->assertStringContainsString('تسویه خارجی با ریال', $html);
    }
}
