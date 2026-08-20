<?php

namespace App\Modules\Stock\Services;

use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\ExternalPaymentIntent;
use App\Modules\Stock\Models\ExternalPaymentReconciliation;
use App\Modules\Stock\Settlement\SettlementChannel;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class ExternalCapitalPaymentService
{
    public function createIntentForAuction(
        Auction $auction,
        int $amountMinor,
        string $currency,
        string $intentKey,
        string $referenceType,
        int|string $referenceId,
        ?string $provider = null,
        array $quoteSnapshot = [],
        array $metadata = [],
        ?\DateTimeInterface $expiresAt = null
    ): ExternalPaymentIntent {
        $this->assertPositive($amountMinor);
        $this->assertKey($intentKey);

        $auction->loadMissing('stock');
        $auction->assertSettlementEligible();
        $channel = (string) $auction->settlement_channel;
        $expectedCurrency = $this->currencyForChannel($channel);
        $currency = strtoupper(trim($currency));
        if ($currency !== $expectedCurrency) {
            throw new InvalidArgumentException("Currency {$currency} does not match settlement channel {$channel}.");
        }

        return DB::transaction(function () use ($auction,$amountMinor,$currency,$intentKey,$referenceType,$referenceId,$provider,$quoteSnapshot,$metadata,$expiresAt,$channel) {
            $existing = ExternalPaymentIntent::query()->where('intent_key',$intentKey)->lockForUpdate()->first();
            if ($existing) {
                return $this->assertSameIntent($existing,$channel,$currency,$amountMinor,$referenceType,$referenceId);
            }

            return ExternalPaymentIntent::create([
                'channel'=>$channel,
                'currency'=>$currency,
                'amount_minor'=>$amountMinor,
                'status'=>ExternalPaymentIntent::CREATED,
                'intent_key'=>$intentKey,
                'reference_type'=>$referenceType,
                'reference_id'=>(string)$referenceId,
                'provider'=>$provider,
                'quote_snapshot'=>$quoteSnapshot ?: null,
                'metadata'=>$metadata,
                'expires_at'=>$expiresAt,
            ]);
        });
    }

    public function markPending(string $intentKey, string $providerIntentId, ?string $provider = null): ExternalPaymentIntent
    {
        $this->assertKey($providerIntentId);
        return DB::transaction(function () use ($intentKey,$providerIntentId,$provider) {
            $intent=$this->lockedIntent($intentKey);
            if ($intent->status===ExternalPaymentIntent::PENDING && $intent->provider_intent_id===$providerIntentId) return $intent;
            if ($intent->status!==ExternalPaymentIntent::CREATED) throw new RuntimeException('Only a created external payment intent can become pending.');
            $intent->forceFill(['status'=>ExternalPaymentIntent::PENDING,'provider'=>$provider?:$intent->provider,'provider_intent_id'=>$providerIntentId])->save();
            return $intent->fresh();
        });
    }

    public function reconcile(
        string $intentKey,
        string $eventKey,
        string $eventType,
        string $resultStatus,
        int $amountMinor,
        string $currency,
        ?string $providerEventId = null,
        ?string $providerPaymentId = null,
        ?string $provider = null,
        array $providerPayload = [],
        array $metadata = [],
        ?\DateTimeInterface $occurredAt = null
    ): ExternalPaymentReconciliation {
        $this->assertKey($eventKey); $this->assertPositive($amountMinor);
        $currency=strtoupper(trim($currency));
        if (!in_array($resultStatus,['pending','confirmed','failed','cancelled'],true)) throw new InvalidArgumentException('Unknown external reconciliation result status.');

        return DB::transaction(function () use ($intentKey,$eventKey,$eventType,$resultStatus,$amountMinor,$currency,$providerEventId,$providerPaymentId,$provider,$providerPayload,$metadata,$occurredAt) {
            $existing=ExternalPaymentReconciliation::query()->where('event_key',$eventKey)->lockForUpdate()->first();
            if ($existing) return $existing;

            $intent=$this->lockedIntent($intentKey);
            if ($intent->currency!==$currency || (int)$intent->amount_minor!==$amountMinor) {
                throw new RuntimeException('External reconciliation amount/currency does not match payment intent.');
            }
            if (in_array($intent->status,[ExternalPaymentIntent::CONFIRMED,ExternalPaymentIntent::FAILED,ExternalPaymentIntent::CANCELLED],true)) {
                throw new RuntimeException('Terminal external payment intent cannot accept a new reconciliation result.');
            }

            $event=ExternalPaymentReconciliation::create([
                'payment_intent_id'=>$intent->id,
                'event_key'=>$eventKey,
                'provider'=>$provider?:$intent->provider,
                'provider_event_id'=>$providerEventId,
                'provider_payment_id'=>$providerPaymentId,
                'event_type'=>$eventType,
                'currency'=>$currency,
                'amount_minor'=>$amountMinor,
                'result_status'=>$resultStatus,
                'provider_payload'=>$this->sanitizeProviderPayload($providerPayload),
                'metadata'=>$metadata,
                'occurred_at'=>$occurredAt?:now(),
            ]);

            $changes=['provider'=>$provider?:$intent->provider,'provider_payment_id'=>$providerPaymentId?:$intent->provider_payment_id];
            if ($resultStatus==='confirmed') { $changes['status']=ExternalPaymentIntent::CONFIRMED; $changes['confirmed_at']=now(); }
            elseif ($resultStatus==='failed') { $changes['status']=ExternalPaymentIntent::FAILED; $changes['failed_at']=now(); }
            elseif ($resultStatus==='cancelled') { $changes['status']=ExternalPaymentIntent::CANCELLED; $changes['cancelled_at']=now(); }
            else { $changes['status']=ExternalPaymentIntent::PENDING; }
            $intent->forceFill($changes)->save();
            return $event;
        });
    }

    public function isConfirmed(string $intentKey): bool
    {
        return ExternalPaymentIntent::query()->where('intent_key',$intentKey)->where('status',ExternalPaymentIntent::CONFIRMED)->exists();
    }

    protected function currencyForChannel(string $channel): string
    {
        return match($channel) {
            SettlementChannel::EXTERNAL_IRR => 'IRR',
            SettlementChannel::EXTERNAL_USD => 'USD',
            default => throw new RuntimeException('External capital rail accepts IRR/USD channels only.'),
        };
    }

    protected function lockedIntent(string $key): ExternalPaymentIntent
    {
        $intent=ExternalPaymentIntent::query()->where('intent_key',$key)->lockForUpdate()->first();
        if(!$intent) throw new RuntimeException('External payment intent not found.');
        return $intent;
    }

    protected function assertSameIntent(ExternalPaymentIntent $i,string $channel,string $currency,int $amount,string $type,int|string $id): ExternalPaymentIntent
    {
        if($i->channel!==$channel||$i->currency!==$currency||(int)$i->amount_minor!==$amount||$i->reference_type!==$type||$i->reference_id!==(string)$id) throw new RuntimeException('External payment intent idempotency key conflicts with existing intent.');
        return $i;
    }

    protected function sanitizeProviderPayload(array $payload): array
    {
        foreach (['card','card_number','pan','cvv','cvc','password','token','access_token','secret','authorization','email','phone'] as $key) unset($payload[$key]);
        return $payload;
    }

    protected function assertPositive(int $amount): void { if($amount<=0) throw new InvalidArgumentException('External payment amount must be a positive integer minor-unit amount.'); }
    protected function assertKey(string $key): void { if(trim($key)==='') throw new InvalidArgumentException('External payment idempotency/reference key is required.'); }
}
