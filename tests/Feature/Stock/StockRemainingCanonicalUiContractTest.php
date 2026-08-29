<?php

namespace Tests\Feature\Stock;

use App\Models\User;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $stock = $this->stock();
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
            'lot_size' => 1,
        ])->load('stock');

        $html = view('Stock::auction_show', [
            'auction' => $auction,
            'orderBook' => collect(),
            'userBids' => collect(),
        ])->render();

        $this->assertStringContainsString('1 گل', $html);
        $this->assertStringContainsString('0.01 بهار', $html);
        $this->assertStringContainsString('عرضه اولیه خزانه EarthCoop', $html);
        $this->assertStringContainsString('تسویه خارجی', $html);
        $this->assertStringNotContainsString('ریال', $html);
    }
}
