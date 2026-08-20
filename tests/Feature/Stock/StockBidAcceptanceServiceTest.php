<?php

namespace Tests\Feature\Stock;

use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\ActiveBaharReservation;
use App\Modules\NajmBahar\Services\ActiveBaharReservationService;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Services\StockBidAcceptanceService;
use App\Modules\Stock\Settlement\SettlementChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockBidAcceptanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_secondary_market_active_bahar_bid_is_reserved_before_acceptance(): void
    {
        $user=User::factory()->create();
        $account=$this->account($user,1000);
        $auction=$this->auction(SettlementChannel::ACTIVE_BAHAR,'secondary','holder');

        $bid=app(StockBidAcceptanceService::class)->acceptActiveBaharBid($user->id,$account->account_number,$auction,25,10,'accept:1');

        $this->assertSame(25,(int)$bid->price_gol);
        $this->assertSame('accept:1:reserve',$bid->reservation_key);
        $this->assertDatabaseHas('najm_active_bahar_reservations',[
            'reservation_key'=>'accept:1:reserve',
            'amount'=>250,
            'status'=>ActiveBaharReservation::RESERVED,
        ]);
        $this->assertSame(750,app(ActiveBaharReservationService::class)->availableActive($account->fresh()));
        $this->assertSame(1000,(int)$account->fresh()->balance_active);
    }

    public function test_secondary_market_external_channel_is_rejected_before_bid_or_reservation(): void
    {
        $user=User::factory()->create();
        $account=$this->account($user,1000);
        $auction=$this->auction(SettlementChannel::EXTERNAL_USD,'secondary','holder');

        try {
            app(StockBidAcceptanceService::class)->acceptActiveBaharBid($user->id,$account->account_number,$auction,25,10,'accept:2');
            $this->fail('Expected external secondary-market rejection.');
        } catch (\RuntimeException $e) {
            $this->assertSame(0,\App\Modules\Stock\Models\Bid::query()->count());
            $this->assertSame(0,ActiveBaharReservation::query()->count());
        }
    }

    public function test_bidder_cannot_reserve_another_users_active_bahar(): void
    {
        $user=User::factory()->create();
        $other=User::factory()->create();
        $otherAccount=$this->account($other,1000);
        $auction=$this->auction(SettlementChannel::ACTIVE_BAHAR,'secondary','holder');
        $this->expectException(\RuntimeException::class);
        app(StockBidAcceptanceService::class)->acceptActiveBaharBid($user->id,$otherAccount->account_number,$auction,25,10,'accept:3');
    }

    public function test_cancellation_releases_active_bahar_reservation_idempotently(): void
    {
        $user=User::factory()->create();
        $account=$this->account($user,1000);
        $auction=$this->auction(SettlementChannel::ACTIVE_BAHAR,'secondary','holder');
        $service=app(StockBidAcceptanceService::class);
        $bid=$service->acceptActiveBaharBid($user->id,$account->account_number,$auction,25,10,'accept:4');
        $cancelled=$service->cancelActiveBaharBid($bid,$user->id,'cancel:4');
        $again=$service->cancelActiveBaharBid($cancelled,$user->id,'cancel:4');

        $this->assertSame('canceled',$again->status);
        $this->assertSame(1000,app(ActiveBaharReservationService::class)->availableActive($account->fresh()));
        $this->assertDatabaseHas('najm_active_bahar_reservations',['reservation_key'=>'accept:4:reserve','status'=>ActiveBaharReservation::RELEASED]);
    }

    public function test_acceptance_key_is_idempotent_and_conflicting_reuse_fails(): void
    {
        $user=User::factory()->create();
        $account=$this->account($user,1000);
        $auction=$this->auction(SettlementChannel::ACTIVE_BAHAR,'secondary','holder');
        $service=app(StockBidAcceptanceService::class);
        $first=$service->acceptActiveBaharBid($user->id,$account->account_number,$auction,25,10,'accept:5');
        $second=$service->acceptActiveBaharBid($user->id,$account->account_number,$auction,25,10,'accept:5');
        $this->assertSame($first->id,$second->id);
        $this->expectException(\RuntimeException::class);
        $service->acceptActiveBaharBid($user->id,$account->account_number,$auction,26,10,'accept:5');
    }

    private function account(User $user,int $active): Account
    {
        return Account::create(['account_number'=>'1'.str_pad((string)$user->id,9,'0',STR_PAD_LEFT),'user_id'=>$user->id,'name'=>'user','type'=>'user','balance'=>$active,'balance_active'=>$active,'balance_faded'=>0,'status'=>1]);
    }

    private function auction(string $channel,string $market,string $supply): Auction
    {
        $stock=Stock::create(['issuer_type'=>'earthcoop','startup_valuation'=>1000,'startup_valuation_gol'=>1000,'total_shares'=>100,'available_shares'=>100,'base_share_price'=>1,'base_share_price_gol'=>10]);
        return Auction::create(['stock_id'=>$stock->id,'market_type'=>$market,'supply_source'=>$supply,'settlement_channel'=>$channel,'quote_unit'=>'gol','shares_count'=>100,'base_price'=>1,'base_price_gol'=>10,'min_bid_gol'=>10,'max_bid_gol'=>100,'start_time'=>now()->subMinute(),'ends_at'=>now()->addHour(),'status'=>'running','type'=>'uniform_price','lot_size'=>100]);
    }
}
