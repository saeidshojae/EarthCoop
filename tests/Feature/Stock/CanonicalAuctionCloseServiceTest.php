<?php

namespace Tests\Feature\Stock;

use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\ActiveBaharReservation;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Holding;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Services\CanonicalAuctionCloseService;
use App\Modules\Stock\Services\StockBidAcceptanceService;
use App\Modules\Stock\Services\StockPayeeAccountService;
use App\Modules\Stock\Settlement\SettlementChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalAuctionCloseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_uniform_price_close_reduces_reservations_and_settles_at_clearing_price(): void
    {
        $capital=Account::create(['account_number'=>'0000000001','name'=>'capital','type'=>'central','balance'=>0,'balance_active'=>0,'balance_faded'=>0,'status'=>1]);
        config(['stock.earthcoop_capital_account_number'=>$capital->account_number]);

        $stock=Stock::create(['issuer_type'=>'earthcoop','startup_valuation'=>1000,'startup_valuation_gol'=>1000,'total_shares'=>100,'available_shares'=>5,'base_share_price'=>1,'base_share_price_gol'=>10]);
        $auction=Auction::create(['stock_id'=>$stock->id,'market_type'=>'primary','supply_source'=>'treasury','settlement_channel'=>SettlementChannel::ACTIVE_BAHAR,'quote_unit'=>'gol','shares_count'=>5,'base_price'=>1,'base_price_gol'=>10,'min_bid_gol'=>10,'max_bid_gol'=>100,'start_time'=>now()->subMinute(),'ends_at'=>now()->addHour(),'status'=>'running','type'=>'uniform_price','lot_size'=>10]);

        $u1=User::factory()->create(); $a1=$this->account($u1,1000);
        $u2=User::factory()->create(); $a2=$this->account($u2,1000);
        $bidder=app(StockBidAcceptanceService::class);
        $b1=$bidder->acceptActiveBaharBid($u1->id,$a1->account_number,$auction,30,3,'cutover:1');
        $b2=$bidder->acceptActiveBaharBid($u2->id,$a2->account_number,$auction,20,4,'cutover:2');

        $result=app(CanonicalAuctionCloseService::class)->close($auction);

        $this->assertSame(5,$result['allocated_shares']);
        $this->assertSame(20,$result['clearing_price_gol']);
        $this->assertSame(940,(int)$a1->fresh()->balance_active);
        $this->assertSame(960,(int)$a2->fresh()->balance_active);
        $this->assertSame(100,(int)$capital->fresh()->balance_active);
        $this->assertSame(3,(int)Holding::where('user_id',$u1->id)->where('stock_id',$stock->id)->value('quantity'));
        $this->assertSame(2,(int)Holding::where('user_id',$u2->id)->where('stock_id',$stock->id)->value('quantity'));
        $this->assertSame(0,(int)$stock->fresh()->available_shares);
        $this->assertSame(60,(int)ActiveBaharReservation::where('reservation_key',$b1->reservation_key)->value('settled_amount'));
        $this->assertSame(40,(int)ActiveBaharReservation::where('reservation_key',$b2->reservation_key)->value('settled_amount'));
    }

    public function test_project_primary_proceeds_go_to_project_stock_payee_not_earthcoop_capital(): void
    {
        $earthcoopCapital=Account::create(['account_number'=>'0000000001','name'=>'earthcoop capital','type'=>'central','balance'=>0,'balance_active'=>0,'balance_faded'=>0,'status'=>1]);
        config(['stock.earthcoop_capital_account_number'=>$earthcoopCapital->account_number]);
        $projectCapital=Account::create(['account_number'=>'2000000001','name'=>'project capital','type'=>'legal_entity','balance'=>0,'balance_active'=>0,'balance_faded'=>0,'status'=>1]);

        $stock=Stock::create([
            'issuer_type'=>'project','issuer_id'=>77,'startup_valuation'=>1000,'startup_valuation_gol'=>1000,
            'total_shares'=>100,'available_shares'=>2,'base_share_price'=>1,'base_share_price_gol'=>10,
        ]);
        app(StockPayeeAccountService::class)->configureProject($stock,$projectCapital);
        $auction=Auction::create([
            'stock_id'=>$stock->id,'market_type'=>'primary','supply_source'=>'treasury',
            'settlement_channel'=>SettlementChannel::ACTIVE_BAHAR,'quote_unit'=>'gol','shares_count'=>2,
            'base_price'=>0,'base_price_gol'=>25,'min_bid_gol'=>25,'max_bid_gol'=>100,
            'start_time'=>now()->subMinute(),'ends_at'=>now()->addHour(),'status'=>'running','type'=>'single_winner','lot_size'=>2,
        ]);
        $buyer=User::factory()->create(); $buyerAccount=$this->account($buyer,1000);
        app(StockBidAcceptanceService::class)->acceptActiveBaharBid($buyer->id,$buyerAccount->account_number,$auction,25,2,'project:primary:1');

        $result=app(CanonicalAuctionCloseService::class)->close($auction);

        $this->assertSame(2,(int)$result['allocated_shares']);
        $this->assertSame(50,(int)$projectCapital->fresh()->balance_active);
        $this->assertSame(0,(int)$earthcoopCapital->fresh()->balance_active);
        $this->assertSame(950,(int)$buyerAccount->fresh()->balance_active);
        $this->assertSame(2,(int)Holding::where('user_id',$buyer->id)->where('stock_id',$stock->id)->value('quantity'));
    }

    public function test_close_fails_closed_when_active_legacy_bid_exists(): void
    {
        $capital=Account::create(['account_number'=>'0000000001','name'=>'capital','type'=>'central','balance'=>0,'balance_active'=>0,'balance_faded'=>0,'status'=>1]);
        config(['stock.earthcoop_capital_account_number'=>$capital->account_number]);
        $stock=Stock::create(['issuer_type'=>'earthcoop','startup_valuation'=>1000,'startup_valuation_gol'=>1000,'total_shares'=>100,'available_shares'=>10,'base_share_price'=>1,'base_share_price_gol'=>10]);
        $auction=Auction::create(['stock_id'=>$stock->id,'market_type'=>'primary','supply_source'=>'treasury','settlement_channel'=>SettlementChannel::ACTIVE_BAHAR,'quote_unit'=>'gol','shares_count'=>10,'base_price'=>1,'base_price_gol'=>10,'start_time'=>now()->subMinute(),'ends_at'=>now()->addHour(),'status'=>'running','type'=>'pay_as_bid','lot_size'=>10]);

        \DB::table('bids')->insert(['auction_id'=>$auction->id,'user_id'=>User::factory()->create()->id,'price'=>10,'quantity'=>1,'status'=>'active','created_at'=>now(),'updated_at'=>now()]);

        $this->expectException(\RuntimeException::class);
        app(CanonicalAuctionCloseService::class)->close($auction);
    }

    private function account(User $user,int $active): Account
    {
        return Account::create(['account_number'=>'1'.str_pad((string)$user->id,9,'0',STR_PAD_LEFT),'user_id'=>$user->id,'name'=>'user','type'=>'user','balance'=>$active,'balance_active'=>$active,'balance_faded'=>0,'status'=>1]);
    }
}
