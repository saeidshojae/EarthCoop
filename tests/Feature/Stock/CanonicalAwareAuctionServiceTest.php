<?php

namespace Tests\Feature\Stock;

use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Services\AuctionService;
use App\Modules\Stock\Services\CanonicalAwareAuctionService;
use App\Modules\Stock\Settlement\SettlementChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalAwareAuctionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_container_resolves_canonical_aware_legacy_guard(): void
    {
        $this->assertInstanceOf(CanonicalAwareAuctionService::class, app(AuctionService::class));
    }

    public function test_legacy_settlement_is_blocked_for_canonical_auction(): void
    {
        $stock=Stock::create(['issuer_type'=>'earthcoop','startup_valuation'=>1000,'startup_valuation_gol'=>1000,'total_shares'=>100,'available_shares'=>100,'base_share_price'=>1,'base_share_price_gol'=>10]);
        $auction=Auction::create(['stock_id'=>$stock->id,'market_type'=>'primary','supply_source'=>'treasury','settlement_channel'=>SettlementChannel::ACTIVE_BAHAR,'quote_unit'=>'gol','shares_count'=>100,'base_price'=>1,'base_price_gol'=>10,'start_time'=>now()->subMinute(),'ends_at'=>now()->subSecond(),'status'=>'running','type'=>'uniform_price','lot_size'=>100]);

        $this->expectException(\RuntimeException::class);
        app(AuctionService::class)->closeAuction($auction);
    }
}
