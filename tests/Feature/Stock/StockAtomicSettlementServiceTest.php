<?php

namespace Tests\Feature\Stock;

use App\Models\User;
use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Services\ActiveBaharReservationService;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Bid;
use App\Modules\Stock\Models\Holding;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Models\StockSettlementAllocation;
use App\Modules\Stock\Pricing\FiatQuoteSnapshot;
use App\Modules\Stock\Services\ExternalCapitalPaymentService;
use App\Modules\Stock\Services\StockAtomicSettlementService;
use App\Modules\Stock\Services\StockBidAcceptanceService;
use App\Modules\Stock\Settlement\SettlementChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockAtomicSettlementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('stock.external_capital.authoritative_quote_sources', ['test-rate', 'manual']);
        config()->set('stock.external_capital.quote_max_age_seconds', 600);
        config()->set('stock.external_capital.quote_future_tolerance_seconds', 30);
    }

    public function test_active_bahar_money_and_asset_settle_atomically_and_retry_is_idempotent(): void
    {
        $user=User::factory()->create();
        $stock=$this->stock(100);
        $auction=$this->auction($stock,SettlementChannel::ACTIVE_BAHAR);
        $payer=$this->payer($user,100);
        $bid=app(StockBidAcceptanceService::class)->acceptActiveBaharBid(
            $user->id,$payer->account_number,$auction,10,5,'accept:settlement:1'
        );

        $payee=Account::create(['account_number'=>'0000000001','name'=>'treasury','type'=>'system','balance'=>0,'balance_active'=>0,'balance_faded'=>0,'status'=>1]);

        $service=app(StockAtomicSettlementService::class);
        $allocation=$service->prepare($auction,$bid,5,'allocation:1',$bid->reservation_key,$payee->account_number);
        $first=$service->settle($allocation);
        $second=$service->settle($first);

        $this->assertSame(StockSettlementAllocation::SETTLED,$second->state);
        $this->assertSame(50,(int)$payer->fresh()->balance_active);
        $this->assertSame(50,(int)$payee->fresh()->balance_active);
        $this->assertSame(95,(int)$stock->fresh()->available_shares);
        $this->assertSame(5,(int)Holding::where('user_id',$user->id)->where('stock_id',$stock->id)->value('quantity'));
        $this->assertDatabaseCount('holding_transactions',1);
    }

    public function test_external_confirmed_payment_with_asset_failure_requires_reconciliation_without_creating_holding(): void
    {
        $user=User::factory()->create();
        $stock=$this->stock(10);
        $auction=$this->auction($stock,SettlementChannel::EXTERNAL_USD,10);
        $bid=$this->externalBid($auction,$user,10,5,'accept:external:atomic');
        $quote=FiatQuoteSnapshot::fromRate(50,'USD',25,2,'test-rate');
        $payments=app(ExternalCapitalPaymentService::class);
        $intent=$payments->createIntentForAuction($auction,$quote,'intent:atomic','auction_bid',$bid->id,'manual');
        $payments->markPending('intent:atomic','provider-intent','manual');
        $payments->reconcile('intent:atomic','event:atomic','confirmed','confirmed',$quote->fiatAmountMinor,'USD','provider-event','provider-payment','manual');

        $service=app(StockAtomicSettlementService::class);
        $allocation=$service->prepare($auction,$bid,5,'allocation:external',null,null,$intent->fresh());

        // Model a real post-confirmation inventory race: the offering and payment
        // were policy-compliant when created, but treasury inventory shrank before
        // the asset leg could settle.
        $stock->forceFill(['available_shares'=>2])->save();

        try { $service->settle($allocation); $this->fail('Expected settlement failure.'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('insufficient treasury shares',strtolower($e->getMessage())); }

        $allocation=$allocation->fresh();
        $this->assertSame(StockSettlementAllocation::RECONCILIATION_REQUIRED,$allocation->state);
        $this->assertSame('confirmed_external',$allocation->money_state);
        $this->assertSame('failed',$allocation->asset_state);
        $this->assertNull(Holding::where('user_id',$user->id)->where('stock_id',$stock->id)->first());
        $this->assertSame(2,(int)$stock->fresh()->available_shares);
    }

    public function test_allocation_key_conflict_fails_closed(): void
    {
        $user=User::factory()->create();
        $stock=$this->stock(100);
        $auction=$this->auction($stock,SettlementChannel::ACTIVE_BAHAR);
        $payer=$this->payer($user,100);
        $bid=app(StockBidAcceptanceService::class)->acceptActiveBaharBid(
            $user->id,$payer->account_number,$auction,10,5,'accept:conflict'
        );
        $payee=Account::create(['account_number'=>'0000000001','name'=>'treasury','type'=>'system','balance'=>0,'balance_active'=>0,'balance_faded'=>0,'status'=>1]);
        app(ActiveBaharReservationService::class)->reserve($payer->account_number,40,'reserve:b','auction_bid',$bid->id);
        $service=app(StockAtomicSettlementService::class);
        $service->prepare($auction,$bid,5,'allocation:same',$bid->reservation_key,$payee->account_number);
        $this->expectException(\RuntimeException::class);
        $service->prepare($auction,$bid,4,'allocation:same','reserve:b',$payee->account_number);
    }

    private function stock(int $available): Stock
    {
        return Stock::create(['issuer_type'=>'earthcoop','startup_valuation'=>1000,'startup_valuation_gol'=>1000,'total_shares'=>100,'available_shares'=>$available,'base_share_price'=>1,'base_share_price_gol'=>10]);
    }

    private function auction(Stock $stock,string $channel,int $sharesCount=100): Auction
    {
        return Auction::create(['stock_id'=>$stock->id,'market_type'=>'primary','supply_source'=>'treasury','settlement_channel'=>$channel,'quote_unit'=>'gol','shares_count'=>$sharesCount,'base_price'=>10,'base_price_gol'=>10,'start_time'=>now(),'ends_at'=>now()->addDay(),'status'=>'running','type'=>'uniform_price','lot_size'=>100]);
    }

    private function payer(User $user,int $active): Account
    {
        return Account::create([
            'account_number'=>'1'.str_pad((string)$user->id,9,'0',STR_PAD_LEFT),
            'user_id'=>$user->id,
            'name'=>'payer',
            'type'=>'user',
            'balance'=>$active,
            'balance_active'=>$active,
            'balance_faded'=>0,
            'status'=>1,
        ]);
    }

    private function externalBid(Auction $auction,User $user,int $priceGol,int $quantity,string $acceptanceKey): Bid
    {
        return Bid::create([
            'acceptance_key'=>$acceptanceKey,
            'auction_id'=>$auction->id,
            'user_id'=>$user->id,
            'price'=>0,
            'price_gol'=>$priceGol,
            'quantity'=>$quantity,
            'status'=>'active',
        ]);
    }
}
