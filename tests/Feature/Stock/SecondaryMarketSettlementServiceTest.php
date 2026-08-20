<?php

namespace Tests\Feature\Stock;

use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Holding;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Services\HoldingReservationService;
use App\Modules\Stock\Services\SecondaryAuctionSupplyService;
use App\Modules\Stock\Services\SecondaryMarketSettlementService;
use App\Modules\Stock\Services\StockBidAcceptanceService;
use App\Modules\Stock\Settlement\SettlementChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecondaryMarketSettlementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_shares_and_buyer_active_bahar_move_atomically_without_touching_treasury(): void
    {
        $seller=User::factory()->create();$buyer=User::factory()->create();
        $sellerAccount=$this->account($seller,0);$buyerAccount=$this->account($buyer,1000);
        $stock=Stock::create(['issuer_type'=>'earthcoop','startup_valuation'=>1000,'startup_valuation_gol'=>1000,'total_shares'=>100,'available_shares'=>40,'base_share_price'=>1,'base_share_price_gol'=>10]);
        $sellerHolding=Holding::create(['user_id'=>$seller->id,'stock_id'=>$stock->id,'quantity'=>20]);
        $auction=$this->auction($stock,10);
        app(SecondaryAuctionSupplyService::class)->attachSellerSupply($auction,$seller->id,10,'seller:1');
        $bid=app(StockBidAcceptanceService::class)->acceptActiveBaharBid($buyer->id,$buyerAccount->account_number,$auction,25,10,'accept:secondary:1');

        $result=app(SecondaryMarketSettlementService::class)->settle($auction,$bid,10,'secondary:settle:1');

        $this->assertSame('settled',$result['status']);
        $this->assertSame(10,(int)$sellerHolding->fresh()->quantity);
        $this->assertSame(10,(int)Holding::where('user_id',$buyer->id)->where('stock_id',$stock->id)->value('quantity'));
        $this->assertSame(40,(int)$stock->fresh()->available_shares);
        $this->assertSame(250,(int)$sellerAccount->fresh()->balance_active);
        $this->assertSame(750,(int)$buyerAccount->fresh()->balance_active);
    }

    public function test_seller_reservation_prevents_double_sell(): void
    {
        $seller=User::factory()->create();$stock=Stock::create(['issuer_type'=>'earthcoop','startup_valuation'=>1000,'startup_valuation_gol'=>1000,'total_shares'=>100,'available_shares'=>40,'base_share_price'=>1,'base_share_price_gol'=>10]);
        $holding=Holding::create(['user_id'=>$seller->id,'stock_id'=>$stock->id,'quantity'=>10]);
        $service=app(HoldingReservationService::class);
        $service->reserve($seller->id,$stock->id,1,8,'seller:r1');
        $this->assertSame(2,$service->availableQuantity($holding->fresh()));
        $this->expectException(\RuntimeException::class);
        $service->reserve($seller->id,$stock->id,2,3,'seller:r2');
    }

    public function test_secondary_settlement_retry_does_not_move_money_or_shares_twice(): void
    {
        $seller=User::factory()->create();$buyer=User::factory()->create();
        $sellerAccount=$this->account($seller,0);$buyerAccount=$this->account($buyer,1000);
        $stock=Stock::create(['issuer_type'=>'earthcoop','startup_valuation'=>1000,'startup_valuation_gol'=>1000,'total_shares'=>100,'available_shares'=>40,'base_share_price'=>1,'base_share_price_gol'=>10]);
        Holding::create(['user_id'=>$seller->id,'stock_id'=>$stock->id,'quantity'=>10]);
        $auction=$this->auction($stock,10);app(SecondaryAuctionSupplyService::class)->attachSellerSupply($auction,$seller->id,10,'seller:retry');
        $bid=app(StockBidAcceptanceService::class)->acceptActiveBaharBid($buyer->id,$buyerAccount->account_number,$auction,20,10,'accept:secondary:retry');
        $service=app(SecondaryMarketSettlementService::class);
        $service->settle($auction,$bid,10,'secondary:settle:retry');
        $again=$service->settle($auction,$bid->fresh(),10,'secondary:settle:retry');
        $this->assertTrue((bool)$again['idempotent']);
        $this->assertSame(200,(int)$sellerAccount->fresh()->balance_active);
        $this->assertSame(800,(int)$buyerAccount->fresh()->balance_active);
    }

    private function account(User $user,int $active): Account
    {
        return Account::create(['account_number'=>'1'.str_pad((string)$user->id,9,'0',STR_PAD_LEFT),'user_id'=>$user->id,'name'=>'user','type'=>'user','balance'=>$active,'balance_active'=>$active,'balance_faded'=>0,'status'=>1]);
    }

    private function auction(Stock $stock,int $shares): Auction
    {
        return Auction::create(['stock_id'=>$stock->id,'market_type'=>'secondary','supply_source'=>'holder','settlement_channel'=>SettlementChannel::ACTIVE_BAHAR,'quote_unit'=>'gol','shares_count'=>$shares,'base_price'=>1,'base_price_gol'=>10,'min_bid_gol'=>10,'max_bid_gol'=>100,'start_time'=>now()->subMinute(),'ends_at'=>now()->addHour(),'status'=>'running','type'=>'single_winner','lot_size'=>$shares]);
    }
}
