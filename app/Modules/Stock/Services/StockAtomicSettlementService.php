<?php

namespace App\Modules\Stock\Services;

use App\Modules\NajmBahar\Models\ActiveBaharReservation;
use App\Modules\NajmBahar\Services\ActiveBaharReservationService;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Bid;
use App\Modules\Stock\Models\ExternalPaymentIntent;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Models\StockSettlementAllocation;
use App\Modules\Stock\Pricing\FiatQuoteSnapshot;
use App\Modules\Stock\Pricing\StockPricingService;
use App\Modules\Stock\Settlement\SettlementChannel;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class StockAtomicSettlementService
{
    public function __construct(
        private readonly StockPricingService $pricing,
        private readonly ActiveBaharReservationService $reservations,
        private readonly HoldingService $holdings,
    ) {}

    public function prepare(
        Auction $auction,
        Bid $bid,
        int $quantity,
        string $allocationKey,
        ?string $reservationKey = null,
        ?string $payeeAccountNumber = null,
        ?ExternalPaymentIntent $externalIntent = null,
        array $metadata = []
    ): StockSettlementAllocation {
        if ($quantity <= 0) throw new InvalidArgumentException('Settlement quantity must be positive.');
        if (trim($allocationKey) === '') throw new InvalidArgumentException('Settlement allocation key is required.');

        $auction->loadMissing('stock');
        $auction->assertSettlementEligible();
        $this->pricing->assertCanonicalAuction($auction);
        $this->pricing->assertCanonicalBid($bid);

        if ((int)$bid->auction_id !== (int)$auction->id) throw new RuntimeException('Bid does not belong to auction.');
        if ($quantity > (int)$bid->quantity) throw new RuntimeException('Settlement quantity exceeds bid quantity.');

        $totalGol = $this->pricing->canonicalBidTotal((int)$bid->price_gol, $quantity);
        $channel = (string)$auction->settlement_channel;

        if ($channel === SettlementChannel::ACTIVE_BAHAR) {
            if (! $reservationKey || ! $payeeAccountNumber) throw new RuntimeException('Active Bahar settlement requires reservation and payee account.');
            if ($externalIntent) throw new RuntimeException('Active Bahar settlement cannot use an external payment intent.');
            $reservation = ActiveBaharReservation::query()->where('reservation_key',$reservationKey)->first();
            if (! $reservation) throw new RuntimeException('Active Bahar reservation not found.');
            if ((int)$reservation->amount !== $totalGol) throw new RuntimeException('Active Bahar reservation amount does not match allocation total.');
            if (! in_array($reservation->status,[ActiveBaharReservation::RESERVED,ActiveBaharReservation::SETTLED],true)) throw new RuntimeException('Active Bahar reservation is not settleable.');
        } else {
            if (! $externalIntent) throw new RuntimeException('External settlement requires a payment intent.');
            if ($reservationKey || $payeeAccountNumber) throw new RuntimeException('External settlement cannot use Najm Bahar reservation fields.');
            if ($externalIntent->channel !== $channel) throw new RuntimeException('External payment intent channel does not match auction.');
            $quote = FiatQuoteSnapshot::fromArray((array)$externalIntent->quote_snapshot);
            if ($quote->golAmount !== $totalGol) throw new RuntimeException('External payment quote Gol amount does not match allocation total.');
            if ((string)$externalIntent->reference_id !== (string)$bid->id) throw new RuntimeException('External payment intent is not bound to this bid.');
        }

        return DB::transaction(function () use ($auction,$bid,$quantity,$allocationKey,$reservationKey,$payeeAccountNumber,$externalIntent,$metadata,$totalGol,$channel) {
            $existing = StockSettlementAllocation::query()->where('allocation_key',$allocationKey)->lockForUpdate()->first();
            if ($existing) return $this->assertSameAllocation($existing,$auction,$bid,$quantity,$totalGol,$channel,$reservationKey,$externalIntent);

            return StockSettlementAllocation::create([
                'allocation_key'=>$allocationKey,
                'auction_id'=>$auction->id,
                'bid_id'=>$bid->id,
                'user_id'=>$bid->user_id,
                'stock_id'=>$auction->stock_id,
                'settlement_channel'=>$channel,
                'quantity'=>$quantity,
                'price_gol'=>(int)$bid->price_gol,
                'total_gol'=>$totalGol,
                'state'=>StockSettlementAllocation::PREPARED,
                'money_state'=>$channel===SettlementChannel::ACTIVE_BAHAR?'reserved':($externalIntent?->status===ExternalPaymentIntent::CONFIRMED?'confirmed':'pending'),
                'asset_state'=>'pending',
                'reservation_key'=>$reservationKey,
                'payee_account_number'=>$payeeAccountNumber,
                'external_payment_intent_id'=>$externalIntent?->id,
                'metadata'=>$metadata,
            ]);
        });
    }

    public function settle(StockSettlementAllocation $allocation): StockSettlementAllocation
    {
        return $allocation->settlement_channel === SettlementChannel::ACTIVE_BAHAR
            ? $this->settleActiveBahar($allocation)
            : $this->settleExternal($allocation);
    }

    public function settleActiveBahar(StockSettlementAllocation $allocation): StockSettlementAllocation
    {
        return DB::transaction(function () use ($allocation) {
            $allocation = StockSettlementAllocation::query()->whereKey($allocation->id)->lockForUpdate()->firstOrFail();
            if ($allocation->state === StockSettlementAllocation::SETTLED) return $allocation;
            if ($allocation->settlement_channel !== SettlementChannel::ACTIVE_BAHAR) throw new RuntimeException('Allocation is not Active Bahar settlement.');

            $allocation->attempts = (int)$allocation->attempts + 1;
            $allocation->last_error = null;
            $allocation->save();

            $stock = Stock::query()->whereKey($allocation->stock_id)->lockForUpdate()->firstOrFail();
            if ((int)$stock->available_shares < (int)$allocation->quantity) throw new RuntimeException('Insufficient treasury shares for allocation.');

            $moneyKey = $allocation->money_settlement_key ?: $allocation->allocation_key.':money';
            $reservation = $this->reservations->settle(
                (string)$allocation->reservation_key,
                (string)$allocation->payee_account_number,
                $moneyKey,
                ['stock_allocation_key'=>$allocation->allocation_key,'auction_id'=>$allocation->auction_id,'bid_id'=>$allocation->bid_id]
            );

            $holdingTx = $this->holdings->settlementIdempotent(
                (int)$allocation->user_id,
                (int)$allocation->stock_id,
                (int)$allocation->quantity,
                $allocation->allocation_key.':asset',
                'Canonical Stock allocation',
                $allocation,
                ['price_gol'=>(int)$allocation->price_gol,'total_gol'=>(int)$allocation->total_gol]
            );

            $stock->available_shares = (int)$stock->available_shares - (int)$allocation->quantity;
            $stock->save();
            if ($allocation->bid_id) Bid::query()->whereKey($allocation->bid_id)->update(['status'=>'won']);

            $allocation->forceFill([
                'state'=>StockSettlementAllocation::SETTLED,
                'money_state'=>$reservation->status,
                'asset_state'=>'settled',
                'money_settlement_key'=>$moneyKey,
                'holding_transaction_id'=>$holdingTx->id,
                'settled_at'=>now(),
            ])->save();

            return $allocation->fresh();
        });
    }

    public function settleExternal(StockSettlementAllocation $allocation): StockSettlementAllocation
    {
        try {
            return DB::transaction(function () use ($allocation) {
                $allocation = StockSettlementAllocation::query()->whereKey($allocation->id)->lockForUpdate()->firstOrFail();
                if ($allocation->state === StockSettlementAllocation::SETTLED) return $allocation;
                if (! in_array($allocation->settlement_channel,[SettlementChannel::EXTERNAL_IRR,SettlementChannel::EXTERNAL_USD],true)) throw new RuntimeException('Allocation is not external settlement.');

                $allocation->attempts = (int)$allocation->attempts + 1;
                $allocation->last_error = null;
                $allocation->save();

                $intent = ExternalPaymentIntent::query()->whereKey($allocation->external_payment_intent_id)->lockForUpdate()->first();
                if (! $intent || $intent->status !== ExternalPaymentIntent::CONFIRMED) throw new RuntimeException('External payment is not confirmed.');
                $quote = FiatQuoteSnapshot::fromArray((array)$intent->quote_snapshot);
                if ($quote->golAmount !== (int)$allocation->total_gol) throw new RuntimeException('Confirmed payment quote no longer matches allocation.');

                $stock = Stock::query()->whereKey($allocation->stock_id)->lockForUpdate()->firstOrFail();
                if ((int)$stock->available_shares < (int)$allocation->quantity) throw new RuntimeException('Confirmed external payment cannot be allocated: insufficient treasury shares.');

                $holdingTx = $this->holdings->settlementIdempotent(
                    (int)$allocation->user_id,
                    (int)$allocation->stock_id,
                    (int)$allocation->quantity,
                    $allocation->allocation_key.':asset',
                    'Canonical Stock allocation after external payment confirmation',
                    $allocation,
                    ['price_gol'=>(int)$allocation->price_gol,'total_gol'=>(int)$allocation->total_gol,'external_payment_intent_id'=>$intent->id]
                );

                $stock->available_shares = (int)$stock->available_shares - (int)$allocation->quantity;
                $stock->save();
                if ($allocation->bid_id) Bid::query()->whereKey($allocation->bid_id)->update(['status'=>'won']);

                $allocation->forceFill([
                    'state'=>StockSettlementAllocation::SETTLED,
                    'money_state'=>'confirmed_external',
                    'asset_state'=>'settled',
                    'holding_transaction_id'=>$holdingTx->id,
                    'settled_at'=>now(),
                ])->save();
                return $allocation->fresh();
            });
        } catch (Throwable $e) {
            DB::transaction(function () use ($allocation,$e) {
                $row=StockSettlementAllocation::query()->whereKey($allocation->id)->lockForUpdate()->first();
                if (! $row || $row->state===StockSettlementAllocation::SETTLED) return;
                $intent=$row->external_payment_intent_id?ExternalPaymentIntent::query()->whereKey($row->external_payment_intent_id)->first():null;
                if ($intent?->status===ExternalPaymentIntent::CONFIRMED) {
                    $row->forceFill([
                        'state'=>StockSettlementAllocation::RECONCILIATION_REQUIRED,
                        'money_state'=>'confirmed_external',
                        'asset_state'=>'failed',
                        'last_error'=>mb_substr($e->getMessage(),0,1000),
                        'reconciliation_required_at'=>now(),
                    ])->save();
                } else {
                    $row->forceFill(['last_error'=>mb_substr($e->getMessage(),0,1000)])->save();
                }
            });
            throw $e;
        }
    }

    protected function assertSameAllocation(StockSettlementAllocation $a,Auction $auction,Bid $bid,int $quantity,int $totalGol,string $channel,?string $reservationKey,?ExternalPaymentIntent $intent): StockSettlementAllocation
    {
        if ((int)$a->auction_id!==(int)$auction->id || (int)$a->bid_id!==(int)$bid->id || (int)$a->quantity!==$quantity || (int)$a->total_gol!==$totalGol || $a->settlement_channel!==$channel || (string)($a->reservation_key??'')!==(string)($reservationKey??'') || (int)($a->external_payment_intent_id??0)!==(int)($intent?->id??0)) {
            throw new RuntimeException('Stock settlement allocation idempotency key conflicts with existing allocation.');
        }
        return $a;
    }
}
