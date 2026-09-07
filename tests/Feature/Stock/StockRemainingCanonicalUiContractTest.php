<?php

namespace Tests\Feature\Stock;

use App\Models\User;
use App\Modules\Stock\Controllers\AuctionController;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Settlement\SettlementChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
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
            'info' => 'اطلاعات پایه عمومی سهام EarthCoop برای کاربران',
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
        $html = view('Stock::admin_stock_info', ['stock' => $stock, 'alerts' => [], 'stats' => null])->render();

        $this->assertStringContainsString('ارزش‌گذاری کل', $html);
        $this->assertStringContainsString('200,000 بهار', $html);
        $this->assertStringContainsString('قیمت پایه هر سهم', $html);
        $this->assertStringContainsString('1 گل', $html);
        $this->assertStringContainsString('0.01 بهار', $html);
        $this->assertStringNotContainsString('ارزش پایه استارتاپ', $html);
        $this->assertStringNotContainsString('ریال', $html);
    }

    public function test_admin_stock_dashboard_restores_operational_and_reporting_navigation(): void
    {
        $stock = $this->stock();
        $html = view('Stock::admin_stock_info', ['stock' => $stock, 'alerts' => [], 'stats' => null])->render();

        $this->assertStringContainsString(route('admin.stock.external-payments.index'), $html);
        $this->assertStringContainsString('تسویه‌های خارجی', $html);
        $this->assertStringContainsString(route('admin.stock-reports.auction-performance'), $html);
        $this->assertStringContainsString('گزارش عملکرد حراج‌ها', $html);
        $this->assertStringContainsString(route('admin.stock-reports.investors'), $html);
        $this->assertStringContainsString('گزارش سهامداران', $html);
        $this->assertStringContainsString(route('admin.stock-reports.financial'), $html);
        $this->assertStringContainsString('گزارش مالی', $html);
    }

    public function test_stock_book_exposes_four_responsive_tabs_public_stock_info_and_full_auction_navigation(): void
    {
        $stock = $this->stock();
        $auction = $this->auction($stock);
        $html = view('Stock::stock_dashboard', ['stock' => $stock, 'auctions' => collect([$auction]), 'soldShares' => 0, 'userHoldings' => collect(), 'walletData' => null])->render();

        $this->assertStringContainsString('data-stockbook-tab="stock-info"', $html);
        $this->assertStringContainsString('اطلاعات پایه سهام', $html);
        $this->assertStringContainsString('data-stockbook-tab="auctions"', $html);
        $this->assertStringContainsString('حراج‌های فعال و برنامه‌ریزی‌شده', $html);
        $this->assertStringContainsString('data-stockbook-tab="holdings"', $html);
        $this->assertStringContainsString('دارایی سهام من', $html);
        $this->assertStringContainsString('data-stockbook-tab="market-settlement"', $html);
        $this->assertStringContainsString('بازار ثانویه و تسویه', $html);
        $this->assertStringContainsString($stock->info, $html);
        $this->assertStringContainsString(route('auction.index'), $html);
        $this->assertStringContainsString('مشاهده همه حراج‌ها', $html);
    }

    public function test_public_auction_list_uses_canonical_gol_bahar_and_stock_ownership_navigation(): void
    {
        $stock = $this->stock();
        $auction = $this->auction($stock);
        $html = view('Stock::auction_list', ['stock' => $stock, 'auctions' => collect([$auction])])->render();

        $this->assertStringContainsString('20,000,000 گل', $html);
        $this->assertStringContainsString('200,000 بهار', $html);
        $this->assertStringContainsString('1 گل', $html);
        $this->assertStringContainsString('0.01 بهار', $html);
        $this->assertStringContainsString('تسویه خارجی با ریال', $html);
        $this->assertStringContainsString(route('stock.book'), $html);
        $this->assertStringContainsString('دفتر سهام', $html);
        $this->assertStringContainsString(route('holding.index'), $html);
        // The unified application layout legitimately contains the user's Najm Bahar
        // wallet navigation. Stock-specific wallet retirement is guarded separately by
        // StockAdminWalletRetirementContractTest, so a rendered-page URL absence check
        // here would conflate global money navigation with the retired Stock wallet.
        $this->assertStringNotContainsString('کیف پول سهام', $html);
        $this->assertStringNotContainsString('ارزش پایه پلتفرم:', $html);
        $this->assertStringNotContainsString('قیمت پایه</strong> ریال', $html);
        $this->assertStringNotContainsString('ریال</td>', $html);
    }

    public function test_public_primary_auction_show_does_not_fall_back_to_legacy_rial_price(): void
    {
        $auction = $this->auction($this->stock());
        $html = view('Stock::auction_show', ['auction' => $auction, 'orderBook' => collect(), 'userBids' => collect()])->render();
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
        $auction = $this->auction($this->stock(), ['end_time' => now()->subMinute(), 'ends_at' => now()->subMinute()]);
        $html = view('Stock::auction_show', ['auction' => $auction, 'orderBook' => collect(), 'userBids' => collect()])->render();
        $this->assertStringContainsString('مهلت ثبت پیشنهاد پایان یافته است', $html);
        $this->assertStringContainsString('پایان‌یافته؛ در انتظار بستن و تسویه', $html);
        $this->assertStringNotContainsString('ثبت پیشنهاد خرید', $html);
    }

    public function test_admin_primary_offering_form_exposes_canonical_settlement_choice(): void
    {
        $stock = $this->stock();
        $html = view('Stock::admin_auction_create', ['stock' => $stock, 'errors' => new ViewErrorBag()])->render();
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
            'stock_id' => $stock->id, 'shares_count' => 500, 'base_price_gol' => 1,
            'settlement_channel' => SettlementChannel::EXTERNAL_IRR,
            'start_time' => now()->addHour()->format('Y-m-d H:i:s'),
            'end_time' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'type' => 'uniform_price', 'settlement_mode' => 'manual', 'lot_size' => 1,
            'info' => 'UAT external offering',
        ]);
        app(AuctionController::class)->adminStore($request);
        $this->assertDatabaseHas('auctions', [
            'stock_id' => $stock->id, 'market_type' => 'primary', 'supply_source' => 'treasury',
            'settlement_channel' => SettlementChannel::EXTERNAL_IRR, 'quote_unit' => 'gol',
            'shares_count' => 500, 'base_price_gol' => 1, 'status' => 'scheduled',
        ]);
    }

    public function test_stock_book_does_not_present_expired_running_auction_as_active(): void
    {
        $stock = $this->stock();
        $expired = $this->auction($stock, ['shares_count' => 321, 'end_time' => now()->subMinute(), 'ends_at' => now()->subMinute()]);
        $active = $this->auction($stock, ['shares_count' => 654, 'end_time' => now()->addDay(), 'ends_at' => now()->addDay()]);
        $html = view('Stock::stock_dashboard', ['stock' => $stock, 'auctions' => collect([$expired, $active]), 'soldShares' => 0, 'userHoldings' => collect(), 'walletData' => null])->render();
        $this->assertStringNotContainsString(route('auction.show', $expired), $html);
        $this->assertStringContainsString(route('auction.show', $active), $html);
        $this->assertStringContainsString('تسویه خارجی با ریال', $html);
    }
}
