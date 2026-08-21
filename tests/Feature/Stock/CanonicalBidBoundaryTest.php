<?php

namespace Tests\Feature\Stock;

use App\Models\User;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Bid;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Settlement\SettlementChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalBidBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_legacy_bid_creation_is_blocked_for_canonical_gol_auction(): void
    {
        $user=User::factory()->create();
        $auction=$this->canonicalAuction();

        $this->expectException(\RuntimeException::class);
        Bid::create([
            'auction_id'=>$auction->id,
            'user_id'=>$user->id,
            'price'=>25,
            'quantity'=>10,
            'status'=>'active',
        ]);
    }

    public function test_active_bahar_canonical_bid_cannot_exist_without_reservation_reference(): void
    {
        $user=User::factory()->create();
        $auction=$this->canonicalAuction();

        $this->expectException(\RuntimeException::class);
        Bid::create([
            'acceptance_key'=>'manual:bad',
            'auction_id'=>$auction->id,
            'user_id'=>$user->id,
            'price'=>0,
            'price_gol'=>25,
            'quantity'=>10,
            'status'=>'active',
        ]);
    }

    private function canonicalAuction(): Auction
    {
        $stock=Stock::create(['issuer_type'=>'earthcoop','startup_valuation'=>1000,'startup_valuation_gol'=>1000,'total_shares'=>100,'available_shares'=>100,'base_share_price'=>1,'base_share_price_gol'=>10]);
        return Auction::create(['stock_id'=>$stock->id,'market_type'=>'secondary','supply_source'=>'holder','settlement_channel'=>SettlementChannel::ACTIVE_BAHAR,'quote_unit'=>'gol','shares_count'=>100,'base_price'=>1,'base_price_gol'=>10,'start_time'=>now()->subMinute(),'ends_at'=>now()->addHour(),'status'=>'running','type'=>'uniform_price','lot_size'=>100]);
    }
}
