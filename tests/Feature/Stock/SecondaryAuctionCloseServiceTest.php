<?php

namespace Tests\Feature\Stock;

use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Holding;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Services\SecondaryAuctionCloseService;
use App\Modules\Stock\Services\SecondaryAuctionSupplyService;
use App\Modules\Stock\Services\StockBidAcceptanceService;
use App\Modules\Stock\Settlement\SettlementChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecondaryAuctionCloseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_uniform_price_uses_clearing_price_without_rewriting_original_bids(): void
    {
        $seller=User::factory()->create();
        $buyerA=User::factory()->create();
        $buyerB=User::factory()->create();
        $sellerAccount=$this->account($seller,0);
        $accountA=$this->account($buyerA,1000);
        $accountB=$this->account($buyerB,1000);

        $stock=Stock::create([
            'issuer_type'=>'earthcoop','startup_valuation'=>1000,'startup_valuation_gol'=>1000,
            'total_shares'=>100,'available_shares'=>40,'base_share_price'=>1,'base_share_price_gol'=>10,
        ]);
        Holding::create(['user_id'=>$seller->id,'stock_id'=>$stock->id,'quantity'=>5]);
        $auction=Auction::create([
            'stock_id'=>$stock->id,'market_type'=>'secondary','supply_source'=>'holder',
            'settlement_channel'=>SettlementChannel::ACTIVE_BAHAR,'quote_unit'=>'gol',
            'shares_count'=>5,'base_price'=>0,'base_price_gol'=>10,'min_bid_gol'=>10,'max_bid_gol'=>100,
            'start_time'=>now()->subHour(),'ends_at'=>now()->subMinute(),'status'=>'running','type'=>'uniform_price','lot_size'=>5,
        ]);
        app(SecondaryAuctionSupplyService::class)->attachSellerSupply($auction,$seller->id,5,'seller:uniform');

        // Temporarily move end time forward only for bid acceptance, then expire again.
        $auction->ends_at=now()->addHour(); $auction->save();
        $accept=app(StockBidAcceptanceService::class);
        $bidA=$accept->acceptActiveBaharBid($buyerA->id,$accountA->account_number,$auction->fresh(),30,3,'secondary:uniform:a');
        $bidB=$accept->acceptActiveBaharBid($buyerB->id,$accountB->account_number,$auction->fresh(),20,3,'secondary:uniform:b');
        $auction->ends_at=now()->subMinute(); $auction->save();

        $result=app(SecondaryAuctionCloseService::class)->close($auction->fresh());

        $this->assertSame(20,(int)$result['clearing_price_gol']);
        $this->assertSame(5,(int)$result['allocated_shares']);
        $this->assertSame(30,(int)$bidA->fresh()->price_gol);
        $this->assertSame(20,(int)$bidB->fresh()->price_gol);
        $this->assertSame(100,(int)$sellerAccount->fresh()->balance_active);
        $this->assertSame(940,(int)$accountA->fresh()->balance_active); // 3 * 20
        $this->assertSame(960,(int)$accountB->fresh()->balance_active); // 2 * 20
        $this->assertSame(0,(int)Holding::where('user_id',$seller->id)->where('stock_id',$stock->id)->value('quantity'));
        $this->assertSame(3,(int)Holding::where('user_id',$buyerA->id)->where('stock_id',$stock->id)->value('quantity'));
        $this->assertSame(2,(int)Holding::where('user_id',$buyerB->id)->where('stock_id',$stock->id)->value('quantity'));
        $this->assertSame(40,(int)$stock->fresh()->available_shares);
    }

    private function account(User $user,int $active): Account
    {
        return Account::create([
            'account_number'=>'1'.str_pad((string)$user->id,9,'0',STR_PAD_LEFT),
            'user_id'=>$user->id,'name'=>'user','type'=>'user','balance'=>$active,
            'balance_active'=>$active,'balance_faded'=>0,'status'=>1,
        ]);
    }
}
