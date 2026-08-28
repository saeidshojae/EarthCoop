<?php

namespace App\Modules\Stock\Services;

use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\ExternalPaymentIntent;
use App\Modules\Stock\Models\ExternalPaymentReconciliation;
use App\Modules\Stock\Models\StockSettlementAllocation;
use App\Modules\Stock\Pricing\ExternalCapitalQuotePolicy;
use App\Modules\Stock\Pricing\FiatQuoteSnapshot;
use App\Modules\Stock\Pricing\StockPricingService;
use App\Modules\Stock\Settlement\SettlementChannel;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ExternalCapitalPaymentService
{
    public function __construct(
        private readonly StockPricingService $pricing,
        private readonly ExternalCapitalQuotePolicy $quotePolicy,
        private readonly EarthCoopPrimaryOfferingPolicy $offeringPolicy
    ) {}

    public function createIntentForAuction(
        Auction $auction,
        FiatQuoteSnapshot $quote,
        string $intentKey,
        string $referenceType,
        int|string $referenceId,
        ?string $provider = null,
        array $metadata = [],
        ?\DateTimeInterface $expiresAt = null
    ): ExternalPaymentIntent {
        $this->assertKey($intentKey);
        $auction->loadMissing('stock');
        $auction->assertSettlementEligible();
        $this->pricing->assertCanonicalAuction($auction);
        $this->quotePolicy->assertEligible($quote);
        $offeringEvidence=$this->offeringPolicy->assertEligible($auction);
        $metadata=array_merge($metadata,['earthcoop_primary_offering'=>$offeringEvidence]);
        $channel=(string)$auction->settlement_channel;
        $expectedCurrency=$this->currencyForChannel($channel);
        $provider=$this->normalizeProvider($provider);
        if($quote->currency!==$expectedCurrency) throw new InvalidArgumentException("Quote currency {$quote->currency} does not match settlement channel {$channel}.");

        return DB::transaction(function () use ($quote,$intentKey,$referenceType,$referenceId,$provider,$metadata,$expiresAt,$channel) {
            $existing=ExternalPaymentIntent::query()->where('intent_key',$intentKey)->lockForUpdate()->first();
            if($existing) return $this->assertSameIntent($existing,$channel,$quote,$referenceType,$referenceId,$provider);
            return ExternalPaymentIntent::create([
                'channel'=>$channel,'currency'=>$quote->currency,'amount_minor'=>$quote->fiatAmountMinor,'status'=>ExternalPaymentIntent::CREATED,
                'intent_key'=>$intentKey,'reference_type'=>$referenceType,'reference_id'=>(string)$referenceId,'provider'=>$provider,
                'quote_snapshot'=>$quote->toArray(),'metadata'=>$metadata,'expires_at'=>$expiresAt,
            ]);
        });
    }

    public function markPending(string $intentKey,string $providerIntentId,?string $provider=null): ExternalPaymentIntent
    {
        $this->assertKey($providerIntentId);
        return DB::transaction(function () use ($intentKey,$providerIntentId,$provider) {
            $intent=$this->lockedIntent($intentKey);
            $resolvedProvider=$this->resolveProvider($intent,$provider);
            if($this->isExpired($intent)) throw new RuntimeException('Expired external payment intent cannot become pending.');
            if($intent->status===ExternalPaymentIntent::PENDING&&$intent->provider_intent_id===$providerIntentId) return $intent;
            if($intent->status!==ExternalPaymentIntent::CREATED) throw new RuntimeException('Only a created external payment intent can become pending.');
            $intent->forceFill(['status'=>ExternalPaymentIntent::PENDING,'provider'=>$resolvedProvider,'provider_intent_id'=>$providerIntentId])->save();
            return $intent->fresh();
        });
    }

    public function reconcile(
        string $intentKey,string $eventKey,string $eventType,string $resultStatus,int $amountMinor,string $currency,
        ?string $providerEventId=null,?string $providerPaymentId=null,?string $provider=null,array $providerPayload=[],array $metadata=[],?\DateTimeInterface $occurredAt=null
    ): ExternalPaymentReconciliation {
        $this->assertKey($eventKey); $this->assertPositive($amountMinor); $currency=strtoupper(trim($currency));
        if(!in_array($resultStatus,['pending','confirmed','failed','cancelled','refunded','reversed'],true)) throw new InvalidArgumentException('Unknown external reconciliation result status.');

        return DB::transaction(function () use ($intentKey,$eventKey,$eventType,$resultStatus,$amountMinor,$currency,$providerEventId,$providerPaymentId,$provider,$providerPayload,$metadata,$occurredAt) {
            $intent=$this->lockedIntent($intentKey);
            $resolvedProvider=$this->resolveProvider($intent,$provider);
            $this->assertStoredQuote($intent);
            $existing=ExternalPaymentReconciliation::query()->where('event_key',$eventKey)->lockForUpdate()->first();
            if($existing) return $this->assertSameReconciliation($existing,$intent,$eventType,$resultStatus,$amountMinor,$currency);

            if($this->isPostConfirmationAdjustment($resultStatus)) {
                $this->assertPostConfirmationAdjustment($intent,$eventType,$resultStatus,$amountMinor,$currency);
            } else {
                if($this->isExpired($intent)&&$resultStatus==='confirmed') throw new RuntimeException('Expired external payment intent cannot be confirmed without a new intent.');
                if($intent->currency!==$currency||(int)$intent->amount_minor!==$amountMinor) throw new RuntimeException('External reconciliation amount/currency does not match payment intent.');
                if(in_array($intent->status,[ExternalPaymentIntent::CONFIRMED,ExternalPaymentIntent::FAILED,ExternalPaymentIntent::CANCELLED,ExternalPaymentIntent::REFUNDED,ExternalPaymentIntent::REVERSED],true)) throw new RuntimeException('Terminal external payment intent cannot accept a new reconciliation result.');
            }

            $event=ExternalPaymentReconciliation::create([
                'payment_intent_id'=>$intent->id,'event_key'=>$eventKey,'provider'=>$resolvedProvider,'provider_event_id'=>$providerEventId,
                'provider_payment_id'=>$providerPaymentId,'event_type'=>$eventType,'currency'=>$currency,'amount_minor'=>$amountMinor,'result_status'=>$resultStatus,
                'provider_payload'=>$this->sanitizeProviderPayload($providerPayload),'metadata'=>$metadata,'occurred_at'=>$occurredAt?:now(),
            ]);

            $changes=['provider'=>$resolvedProvider,'provider_payment_id'=>$providerPaymentId?:$intent->provider_payment_id];
            if($resultStatus==='confirmed'){ $changes['status']=ExternalPaymentIntent::CONFIRMED; $changes['confirmed_at']=now(); }
            elseif($resultStatus==='failed'){ $changes['status']=ExternalPaymentIntent::FAILED; $changes['failed_at']=now(); }
            elseif($resultStatus==='cancelled'){ $changes['status']=ExternalPaymentIntent::CANCELLED; $changes['cancelled_at']=now(); }
            elseif($resultStatus==='refunded'){ $changes['status']=ExternalPaymentIntent::REFUNDED; $this->cancelUnsettledAllocations($intent,'refunded_external'); }
            elseif($resultStatus==='reversed'){ $changes['status']=ExternalPaymentIntent::REVERSED; $this->cancelUnsettledAllocations($intent,'reversed_external'); }
            else { $changes['status']=ExternalPaymentIntent::PENDING; }
            $intent->forceFill($changes)->save();
            return $event;
        });
    }

    public function isConfirmed(string $intentKey): bool
    {
        return ExternalPaymentIntent::query()->where('intent_key',$intentKey)->where('status',ExternalPaymentIntent::CONFIRMED)->exists();
    }

    protected function assertStoredQuote(ExternalPaymentIntent $intent): FiatQuoteSnapshot
    {
        $quote=FiatQuoteSnapshot::fromArray((array)$intent->quote_snapshot);
        if($quote->currency!==$intent->currency||$quote->fiatAmountMinor!==(int)$intent->amount_minor) throw new RuntimeException('Stored fiat quote snapshot does not match payment intent.');
        return $quote;
    }

    protected function currencyForChannel(string $channel): string
    {
        return match($channel){ SettlementChannel::EXTERNAL_IRR=>'IRR',SettlementChannel::EXTERNAL_USD=>'USD',default=>throw new RuntimeException('External capital rail accepts IRR/USD channels only.') };
    }

    protected function lockedIntent(string $key): ExternalPaymentIntent
    {
        $intent=ExternalPaymentIntent::query()->where('intent_key',$key)->lockForUpdate()->first(); if(!$intent) throw new RuntimeException('External payment intent not found.'); return $intent;
    }

    protected function assertSameIntent(ExternalPaymentIntent $i,string $channel,FiatQuoteSnapshot $quote,string $type,int|string $id,?string $provider): ExternalPaymentIntent
    {
        if($i->channel!==$channel||$i->currency!==$quote->currency||(int)$i->amount_minor!==$quote->fiatAmountMinor||$i->reference_type!==$type||$i->reference_id!==(string)$id) throw new RuntimeException('External payment intent idempotency key conflicts with existing intent.');
        if($this->normalizeProvider($i->provider)!==$provider) throw new RuntimeException('External payment provider conflicts with existing intent.');
        $stored=$this->assertStoredQuote($i);
        if($stored->toArray()!==$quote->toArray()) throw new RuntimeException('External payment intent quote snapshot conflicts with existing intent.');
        return $i;
    }

    protected function assertSameReconciliation(ExternalPaymentReconciliation $e,ExternalPaymentIntent $intent,string $eventType,string $status,int $amount,string $currency): ExternalPaymentReconciliation
    {
        if((int)$e->payment_intent_id!==(int)$intent->id||$e->event_type!==$eventType||$e->result_status!==$status||(int)$e->amount_minor!==$amount||$e->currency!==$currency) throw new RuntimeException('External reconciliation event key conflicts with an existing event.'); return $e;
    }

    protected function isPostConfirmationAdjustment(string $status): bool
    {
        return in_array($status,[ExternalPaymentIntent::REFUNDED,ExternalPaymentIntent::REVERSED],true);
    }

    protected function assertPostConfirmationAdjustment(ExternalPaymentIntent $intent,string $eventType,string $status,int $amount,string $currency): void
    {
        if($intent->status!==ExternalPaymentIntent::CONFIRMED) throw new RuntimeException('External refund/reversal requires a confirmed payment intent.');
        if($intent->currency!==$currency||(int)$intent->amount_minor!==$amount) throw new RuntimeException('External refund/reversal must cover the full amount and original currency.');
        $requiredEventType=$status===ExternalPaymentIntent::REFUNDED?'payment_refunded':'payment_reversed';
        if($eventType!==$requiredEventType) throw new RuntimeException('External refund/reversal event type does not match result status.');
        $settledAllocation=StockSettlementAllocation::query()
            ->where('external_payment_intent_id',$intent->id)
            ->where(function($query){ $query->where('state',StockSettlementAllocation::SETTLED)->orWhere('asset_state','settled'); })
            ->lockForUpdate()
            ->exists();
        if($settledAllocation) throw new RuntimeException('External refund/reversal is blocked after asset settlement; explicit asset reversal is required.');
    }

    protected function cancelUnsettledAllocations(ExternalPaymentIntent $intent,string $moneyState): void
    {
        StockSettlementAllocation::query()
            ->where('external_payment_intent_id',$intent->id)
            ->where('state','!=',StockSettlementAllocation::SETTLED)
            ->where('asset_state','!=','settled')
            ->lockForUpdate()
            ->get()
            ->each(function(StockSettlementAllocation $allocation) use ($moneyState): void {
                $allocation->forceFill([
                    'state'=>StockSettlementAllocation::CANCELLED,
                    'money_state'=>$moneyState,
                ])->save();
            });
    }

    protected function resolveProvider(ExternalPaymentIntent $intent,?string $provider): ?string
    {
        $stored=$this->normalizeProvider($intent->provider);
        $candidate=$this->normalizeProvider($provider);
        if($stored!==null&&$candidate!==null&&$stored!==$candidate) throw new RuntimeException('External payment provider cannot change after intent creation.');
        return $stored??$candidate;
    }

    protected function normalizeProvider(?string $provider): ?string
    {
        if($provider===null) return null;
        $provider=trim($provider);
        return $provider===''?null:$provider;
    }

    protected function sanitizeProviderPayload(array $payload): array
    {
        $sensitive=['card','card_number','pan','cvv','cvc','password','token','access_token','secret','authorization','email','phone']; $clean=[];
        foreach($payload as $key=>$value){ if(in_array(strtolower((string)$key),$sensitive,true)) continue; $clean[$key]=is_array($value)?$this->sanitizeProviderPayload($value):$value; } return $clean;
    }

    protected function isExpired(ExternalPaymentIntent $intent): bool { return $intent->expires_at!==null&&$intent->expires_at->isPast(); }
    protected function assertPositive(int $amount): void { if($amount<=0) throw new InvalidArgumentException('External payment amount must be a positive integer minor-unit amount.'); }
    protected function assertKey(string $key): void { if(trim($key)==='') throw new InvalidArgumentException('External payment idempotency/reference key is required.'); }
}
