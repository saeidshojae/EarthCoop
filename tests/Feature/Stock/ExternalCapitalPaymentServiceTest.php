<?php

namespace Tests\Feature\Stock;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\ExternalPaymentIntent;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Pricing\FiatQuoteSnapshot;
use App\Modules\Stock\Services\ExternalCapitalPaymentService;
use App\Modules\Stock\Settlement\SettlementChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalCapitalPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('stock.external_capital.authoritative_quote_sources', ['test-rate', 'manual', 'official-fx']);
        config()->set('stock.external_capital.quote_max_age_seconds', 600);
        config()->set('stock.external_capital.quote_future_tolerance_seconds', 30);
    }

    public function test_external_intent_requires_eligible_canonical_gol_auction(): void
    {
        $service=app(ExternalCapitalPaymentService::class);
        $auction=$this->auction('earthcoop','primary','treasury',SettlementChannel::EXTERNAL_USD);
        $quote=FiatQuoteSnapshot::fromRate(1000,'USD',25,2,'test-rate');
        $intent=$service->createIntentForAuction($auction,$quote,'intent:1','auction_bid',7,'provider-x');
        $this->assertSame(ExternalPaymentIntent::CREATED,$intent->status);
        $this->assertSame('USD',$intent->currency);
        $this->assertSame(12500,(int)$intent->amount_minor);
        $this->assertSame(1000,(int)data_get($intent->quote_snapshot,'gol_amount'));
    }

    public function test_external_intent_rejects_quote_from_unapproved_source(): void
    {
        config()->set('stock.external_capital.authoritative_quote_sources', ['official-fx']);

        $service=app(ExternalCapitalPaymentService::class);
        $auction=$this->auction('earthcoop','primary','treasury',SettlementChannel::EXTERNAL_USD);
        $quote=FiatQuoteSnapshot::fromRate(1000,'USD',25,2,'manual');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('authoritative');
        $service->createIntentForAuction($auction,$quote,'intent:source','auction_bid',70,'provider-x');
    }

    public function test_external_intent_rejects_stale_quote(): void
    {
        $service=app(ExternalCapitalPaymentService::class);
        $auction=$this->auction('earthcoop','primary','treasury',SettlementChannel::EXTERNAL_USD);
        $quote=FiatQuoteSnapshot::fromRate(1000,'USD',25,2,'official-fx',now()->subMinutes(11));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('expired');
        $service->createIntentForAuction($auction,$quote,'intent:stale','auction_bid',71,'provider-x');
    }

    public function test_external_intent_rejects_quote_timestamp_too_far_in_future(): void
    {
        $service=app(ExternalCapitalPaymentService::class);
        $auction=$this->auction('earthcoop','primary','treasury',SettlementChannel::EXTERNAL_USD);
        $quote=FiatQuoteSnapshot::fromRate(1000,'USD',25,2,'official-fx',now()->addMinutes(2));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('future');
        $service->createIntentForAuction($auction,$quote,'intent:future','auction_bid',72,'provider-x');
    }

    public function test_secondary_market_external_intent_fails_closed(): void
    {
        $service=app(ExternalCapitalPaymentService::class);
        $auction=$this->auction('earthcoop','secondary','holder',SettlementChannel::EXTERNAL_USD);
        $this->expectException(\RuntimeException::class);
        $service->createIntentForAuction($auction,FiatQuoteSnapshot::fromRate(1000,'USD',25,2,'test-rate'),'intent:2','auction_bid',8);
    }

    public function test_currency_must_match_external_channel(): void
    {
        $service=app(ExternalCapitalPaymentService::class);
        $auction=$this->auction('earthcoop','primary','treasury',SettlementChannel::EXTERNAL_IRR);
        $this->expectException(\InvalidArgumentException::class);
        $service->createIntentForAuction($auction,FiatQuoteSnapshot::fromRate(1000,'USD',25,2,'test-rate'),'intent:3','auction_bid',9);
    }

    public function test_confirmed_reconciliation_does_not_credit_najm_bahar_and_redacts_provider_secrets(): void
    {
        $account=Account::create(['account_number'=>'1000000001','name'=>'user','type'=>'user','balance'=>1000,'balance_active'=>1000,'balance_faded'=>0,'status'=>1]);
        $service=app(ExternalCapitalPaymentService::class);
        $auction=$this->auction('earthcoop','primary','treasury',SettlementChannel::EXTERNAL_IRR);
        $quote=FiatQuoteSnapshot::fromRate(1000,'IRR',500,1,'test-rate');
        $service->createIntentForAuction($auction,$quote,'intent:4','auction_bid',10,'manual');
        $service->markPending('intent:4','provider-intent-4','manual');
        $event=$service->reconcile('intent:4','event:4','payment_confirmed','confirmed',500000,'IRR','provider-event-4','provider-payment-4','manual',['token'=>'must-not-persist','receipt'=>'R-4','nested'=>['card_number'=>'4111111111111111','safe'=>'ok']]);

        $this->assertTrue($service->isConfirmed('intent:4'));
        $this->assertSame(1000,(int)$account->fresh()->balance);
        $this->assertSame(1000,(int)$account->fresh()->balance_active);
        $payload=(array)$event->fresh()->provider_payload;
        $this->assertArrayNotHasKey('token',$payload);
        $this->assertSame('R-4',$payload['receipt']??null);
        $this->assertArrayNotHasKey('card_number',(array)($payload['nested']??[]));
        $this->assertSame('ok',data_get($payload,'nested.safe'));
    }

    public function test_reconciliation_amount_mismatch_is_rejected(): void
    {
        $service=app(ExternalCapitalPaymentService::class);
        $auction=$this->auction('earthcoop','primary','treasury',SettlementChannel::EXTERNAL_USD);
        $service->createIntentForAuction($auction,FiatQuoteSnapshot::fromRate(1000,'USD',25,2,'test-rate'),'intent:5','auction_bid',11);
        $this->expectException(\RuntimeException::class);
        $service->reconcile('intent:5','event:5','payment_confirmed','confirmed',12499,'USD');
    }

    public function test_event_key_replay_is_idempotent_but_conflicting_reuse_fails(): void
    {
        $service=app(ExternalCapitalPaymentService::class);
        $auction=$this->auction('earthcoop','primary','treasury',SettlementChannel::EXTERNAL_USD);
        $service->createIntentForAuction($auction,FiatQuoteSnapshot::fromRate(1000,'USD',25,2,'test-rate'),'intent:6','auction_bid',12);
        $first=$service->reconcile('intent:6','event:6','payment_pending','pending',12500,'USD');
        $second=$service->reconcile('intent:6','event:6','payment_pending','pending',12500,'USD');
        $this->assertSame($first->id,$second->id);
        $this->expectException(\RuntimeException::class);
        $service->reconcile('intent:6','event:6','payment_confirmed','confirmed',12500,'USD');
    }

    public function test_expired_intent_cannot_be_confirmed(): void
    {
        $service=app(ExternalCapitalPaymentService::class);
        $auction=$this->auction('earthcoop','primary','treasury',SettlementChannel::EXTERNAL_IRR);
        $service->createIntentForAuction($auction,FiatQuoteSnapshot::fromRate(1000,'IRR',500,1,'test-rate'),'intent:7','auction_bid',13,null,[],now()->subMinute());
        $this->expectException(\RuntimeException::class);
        $service->reconcile('intent:7','event:7','payment_confirmed','confirmed',500000,'IRR');
    }

    public function test_legacy_decimal_auction_cannot_create_new_external_intent(): void
    {
        $stock=Stock::create(['issuer_type'=>'earthcoop','startup_valuation'=>1000000,'total_shares'=>100000000,'available_shares'=>1000000,'base_share_price'=>0.01]);
        $auction=Auction::create(['stock_id'=>$stock->id,'market_type'=>'primary','supply_source'=>'treasury','settlement_channel'=>SettlementChannel::EXTERNAL_USD,'quote_unit'=>'bahar','shares_count'=>100,'base_price'=>1,'start_time'=>now(),'ends_at'=>now()->addDay(),'status'=>'running','type'=>'uniform_price','lot_size'=>1]);
        $this->expectException(\RuntimeException::class);
        app(ExternalCapitalPaymentService::class)->createIntentForAuction($auction,FiatQuoteSnapshot::fromRate(1000,'USD',25,2,'test-rate'),'intent:legacy','auction_bid',14);
    }

    private function auction(string $issuer,string $market,string $supply,string $channel): Auction
    {
        $stock=Stock::create(['issuer_type'=>$issuer,'startup_valuation'=>1000000,'startup_valuation_gol'=>100000000,'total_shares'=>100000000,'available_shares'=>1000000,'base_share_price'=>0.01,'base_share_price_gol'=>1]);
        return Auction::create(['stock_id'=>$stock->id,'market_type'=>$market,'supply_source'=>$supply,'settlement_channel'=>$channel,'quote_unit'=>'gol','shares_count'=>100,'base_price'=>1,'base_price_gol'=>10,'start_time'=>now(),'ends_at'=>now()->addDay(),'status'=>'running','type'=>'uniform_price','lot_size'=>1]);
    }
}
