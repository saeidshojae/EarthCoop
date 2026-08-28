<?php

namespace Tests\Feature\Stock;

use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Pricing\FiatQuoteSnapshot;
use App\Modules\Stock\Pricing\StockPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockIntegerGolPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_and_auction_canonical_prices_are_integer_gol(): void
    {
        $stock=Stock::create(['issuer_type'=>'earthcoop','startup_valuation'=>1,'total_shares'=>100,'available_shares'=>100,'base_share_price'=>0.01]);
        $pricing=app(StockPricingService::class);
        $pricing->setStockPrice($stock,25);
        $this->assertSame(25,(int)$stock->fresh()->base_share_price_gol);
        $this->assertSame(2500,(int)$stock->fresh()->startup_valuation_gol);

        $auction=Auction::create(['stock_id'=>$stock->id,'market_type'=>'primary','supply_source'=>'treasury','settlement_channel'=>'active_bahar','quote_unit'=>'bahar','shares_count'=>10,'base_price'=>1,'start_time'=>now(),'ends_at'=>now()->addDay(),'status'=>'running','type'=>'uniform_price','lot_size'=>1]);
        $pricing->configureAuction($auction,30,25,100);
        $auction=$auction->fresh();
        $this->assertTrue($auction->hasCanonicalGolPricing());
        $this->assertSame('gol',$auction->quote_unit);
        $this->assertSame(30,(int)$auction->base_price_gol);
    }

    public function test_bid_total_uses_only_integer_arithmetic(): void
    {
        $this->assertSame(300,app(StockPricingService::class)->canonicalBidTotal(30,10));
    }

    public function test_quote_snapshot_uses_deterministic_half_up_integer_rounding(): void
    {
        $quote=FiatQuoteSnapshot::fromRate(3,'USD',5,2,'unit-test');
        $this->assertSame(8,$quote->fiatAmountMinor);
        $this->assertSame('half_up_integer',$quote->toArray()['rounding']);
        $restored=FiatQuoteSnapshot::fromArray($quote->toArray());
        $this->assertSame($quote->toArray(),$restored->toArray());
    }

    public function test_inconsistent_quote_snapshot_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FiatQuoteSnapshot(100,'USD',999,25,2,'unit-test',now()->toAtomString());
    }

    public function test_legacy_decimal_values_are_not_implicitly_converted_to_gol(): void
    {
        $stock=Stock::create(['issuer_type'=>'earthcoop','startup_valuation'=>1000000,'total_shares'=>100000000,'available_shares'=>1000000,'base_share_price'=>0.01]);
        $this->assertNull($stock->fresh()->base_share_price_gol);
        $this->assertNull($stock->fresh()->startup_valuation_gol);
    }
}
