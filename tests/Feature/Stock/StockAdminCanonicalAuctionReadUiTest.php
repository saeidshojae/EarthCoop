<?php

namespace Tests\Feature\Stock;

use App\Models\User;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Settlement\SettlementChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class StockAdminCanonicalAuctionReadUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function auction(): Auction
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

        return Auction::create([
            'stock_id' => $stock->id,
            'market_type' => 'primary',
            'supply_source' => 'treasury',
            'settlement_channel' => SettlementChannel::EXTERNAL_IRR,
            'quote_unit' => 'gol',
            'shares_count' => 1_000_000,
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
    }

    public function test_admin_auction_list_uses_canonical_market_and_price_vocabulary(): void
    {
        $auction = $this->auction();
        $paginator = new LengthAwarePaginator(collect([$auction]), 1, 25, 1);

        $html = view('Stock::admin_auction_list', [
            'auctions' => $paginator,
            'stats' => ['total_auctions'=>1,'running_auctions'=>0,'scheduled_auctions'=>1,'settled_auctions'=>0,'canceled_auctions'=>0,'total_bids'=>0,'total_volume'=>0,'total_capital'=>0],
            'statusCounts' => ['running'=>0,'scheduled'=>1,'settled'=>0,'canceled'=>0],
            'totalVolume' => 0,
            'chartData' => ['labels'=>[],'volumes'=>[],'prices'=>[],'counts'=>[]],
        ])->render();

        $this->assertStringContainsString('قیمت پایه (گل)', $html);
        $this->assertStringContainsString('بازار اولیه', $html);
        $this->assertStringContainsString('خزانه EarthCoop', $html);
        $this->assertStringContainsString('تسویه خارجی ریالی', $html);
        $this->assertStringNotContainsString('قیمت پایه (ریال)', $html);
    }

    public function test_admin_auction_show_exposes_canonical_asset_and_settlement_identity(): void
    {
        $auction = $this->auction()->load('stock');

        $html = view('Stock::admin_auction_show', [
            'auction' => $auction,
            'orderBook' => collect(),
            'settlementStats' => null,
        ])->render();

        $this->assertStringContainsString('۱۲ گل', $html);
        $this->assertStringContainsString('۰٫۱۲ بهار', $html);
        $this->assertStringContainsString('عرضه اولیه خزانه EarthCoop', $html);
        $this->assertStringContainsString('تسویه خارجی', $html);
        $this->assertStringNotContainsString('قیمت پایه هر سهم (ریال)', $html);
    }
}
