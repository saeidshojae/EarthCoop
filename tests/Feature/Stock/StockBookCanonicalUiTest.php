<?php

namespace Tests\Feature\Stock;

use App\Modules\Stock\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class StockBookCanonicalUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_book_presents_asset_value_in_gol_bahar_and_not_as_a_second_money_wallet(): void
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

        $html = view('Stock::stock_dashboard', [
            'stock' => $stock,
            'auctions' => new Collection(),
            'soldShares' => 0,
            'userHoldings' => null,
            'walletData' => null,
        ])->render();

        $this->assertStringContainsString('دفتر سهام EarthCoop', $html);
        $this->assertStringContainsString('ارزش‌گذاری کل', $html);
        $this->assertStringContainsString('قیمت پایه هر سهم', $html);
        $this->assertStringContainsString('۱۲ گل', $html);
        $this->assertStringContainsString('۰٫۱۲ بهار', $html);
        $this->assertStringContainsString('عرضه اولیه خزانه', $html);
        $this->assertStringContainsString('حداکثر ۱۰٪ از کل سهام', $html);
        $this->assertStringContainsString('بازار ثانویه هنوز فعال نشده است', $html);
        $this->assertStringContainsString('تسویه خارجی فقط در عرضه اولیه خزانه', $html);
        $this->assertStringNotContainsString('ارزش پایه هر سهم (ریال)', $html);
        $this->assertStringNotContainsString('کیف پول سهام', $html);
    }
}
