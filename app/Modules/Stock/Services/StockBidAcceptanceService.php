<?php

namespace App\Modules\Stock\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Services\ActiveBaharReservationService;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Bid;
use App\Modules\Stock\Models\ExternalPaymentIntent;
use App\Modules\Stock\Pricing\FiatQuoteSnapshot;
use App\Modules\Stock\Pricing\StockPricingService;
use App\Modules\Stock\Settlement\SettlementChannel;
use App\Modules\Stock\Settlement\SettlementEligibilityPolicy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class StockBidAcceptanceService
{
    public function __construct(
        private readonly StockPricingService $pricing,
        private readonly ActiveBaharReservationService $reservations,
        private readonly SettlementEligibilityPolicy $eligibility,
        private readonly ExternalCapitalPaymentService $externalPayments,
    ) {}

    public function acceptActiveBaharBid(
        int $userId,
        string $payerAccountNumber,
        Auction $auction,
        int $priceGol,
        int $quantity,
        string $acceptanceKey,
        array $metadata = []
    ): Bid {
        if ($userId <= 0) throw new InvalidArgumentException('User id is required.');
        if (trim($payerAccountNumber) === '') throw new InvalidArgumentException('Payer account number is required.');
        if (trim($acceptanceKey) === '') throw new InvalidArgumentException('Bid acceptance key is required.');
        if (! $auction->isActive()) throw new RuntimeException('Auction is not active.');

        $payer = Account::query()
            ->where('account_number', $payerAccountNumber)
            ->where('type', 'user')
            ->where('user_id', $userId)
            ->first();
        if (! $payer) throw new RuntimeException('Payer account is not the bidder main Najm Bahar account.');

        $this->assertCanonicalAuction($auction);

        if ((string)$auction->settlement_channel !== SettlementChannel::ACTIVE_BAHAR) {
            throw new RuntimeException('This canonical bid acceptance path is Active Bahar only.');
        }

        $this->assertBidBounds($auction, $priceGol, $quantity);

        $totalGol = $this->pricing->canonicalBidTotal($priceGol, $quantity);
        $reservationKey = $acceptanceKey . ':reserve';

        return DB::transaction(function () use ($userId,$payerAccountNumber,$auction,$priceGol,$quantity,$acceptanceKey,$metadata,$totalGol,$reservationKey) {
            $existing = Bid::query()->where('acceptance_key',$acceptanceKey)->lockForUpdate()->first();
            if ($existing) {
                if ((int)$existing->user_id !== $userId
                    || (int)$existing->auction_id !== (int)$auction->id
                    || (int)$existing->price_gol !== $priceGol
                    || (int)$existing->quantity !== $quantity
                    || (string)$existing->reservation_key !== $reservationKey) {
                    throw new RuntimeException('Bid acceptance key conflicts with an existing bid.');
                }
                return $existing;
            }

            $this->reservations->reserve(
                $payerAccountNumber,
                $totalGol,
                $reservationKey,
                'stock_bid',
                $acceptanceKey,
                array_merge($metadata,['auction_id'=>(int)$auction->id,'user_id'=>$userId])
            );

            return Bid::create([
                'acceptance_key'=>$acceptanceKey,
                'auction_id'=>$auction->id,
                'user_id'=>$userId,
                'price'=>0,
                'price_gol'=>$priceGol,
                'reservation_key'=>$reservationKey,
                'quantity'=>$quantity,
                'status'=>'active',
            ]);
        });
    }

    public function initiateExternalCapitalBid(
        int $userId,
        Auction $auction,
        int $priceGol,
        int $quantity,
        string $acceptanceKey,
        FiatQuoteSnapshot $quote,
        string $provider,
        array $metadata = []
    ): Bid {
        if ($userId <= 0) throw new InvalidArgumentException('User id is required.');
        if (trim($acceptanceKey) === '') throw new InvalidArgumentException('Bid acceptance key is required.');
        if (trim($provider) === '') throw new InvalidArgumentException('External payment provider is required.');
        if (! $auction->isActive()) throw new RuntimeException('Auction is not active.');

        $this->assertCanonicalAuction($auction);
        if (! SettlementChannel::isExternal((string)$auction->settlement_channel)) {
            throw new RuntimeException('This bid funding path is external-capital only.');
        }
        $this->assertBidBounds($auction, $priceGol, $quantity);

        $totalGol = $this->pricing->canonicalBidTotal($priceGol, $quantity);
        if ((int)$quote->golAmount !== $totalGol) {
            throw new RuntimeException('External funding quote Gol total does not match the bid total.');
        }

        $expectedCurrency = match ((string)$auction->settlement_channel) {
            SettlementChannel::EXTERNAL_IRR => 'IRR',
            SettlementChannel::EXTERNAL_USD => 'USD',
            default => throw new RuntimeException('Unsupported external settlement channel.'),
        };
        if (strtoupper($quote->currency) !== $expectedCurrency) {
            throw new RuntimeException('External funding quote currency does not match the auction settlement channel.');
        }

        return DB::transaction(function () use ($userId,$auction,$priceGol,$quantity,$acceptanceKey,$quote,$provider,$metadata,$totalGol) {
            $existing = Bid::query()->where('acceptance_key',$acceptanceKey)->lockForUpdate()->first();
            if ($existing) {
                if ((int)$existing->user_id !== $userId
                    || (int)$existing->auction_id !== (int)$auction->id
                    || (int)$existing->price_gol !== $priceGol
                    || (int)$existing->quantity !== $quantity) {
                    throw new RuntimeException('Bid acceptance key conflicts with an existing bid.');
                }
                if (! $existing->external_payment_intent_id) {
                    throw new RuntimeException('Existing external bid is missing its funding intent.');
                }
                return $existing;
            }

            $bid = Bid::create([
                'acceptance_key'=>$acceptanceKey,
                'auction_id'=>$auction->id,
                'user_id'=>$userId,
                'price'=>0,
                'price_gol'=>$priceGol,
                'reservation_key'=>null,
                'external_payment_intent_id'=>null,
                'quantity'=>$quantity,
                'status'=>'awaiting_funding',
            ]);

            $intent = $this->externalPayments->createIntentForAuction(
                $auction,
                $quote,
                $acceptanceKey . ':funding',
                'stock_bid',
                (string)$bid->id,
                $provider,
                array_merge($metadata, [
                    'bid_acceptance_key'=>$acceptanceKey,
                    'auction_id'=>(int)$auction->id,
                    'user_id'=>$userId,
                    'price_gol'=>$priceGol,
                    'quantity'=>$quantity,
                    'total_gol'=>$totalGol,
                ])
            );

            $bid->external_payment_intent_id = $intent->id;
            $bid->save();

            return $bid->fresh();
        });
    }

    public function activateExternallyFundedBid(Bid $bid): Bid
    {
        return DB::transaction(function () use ($bid) {
            $bid = Bid::query()->whereKey($bid->id)->lockForUpdate()->firstOrFail();
            if ($bid->status === 'active') return $bid;
            if ($bid->status !== 'awaiting_funding') {
                throw new RuntimeException('Only a bid awaiting funding can be activated.');
            }
            if (! $bid->external_payment_intent_id) {
                throw new RuntimeException('External bid has no funding intent.');
            }

            $bid->loadMissing('auction.stock');
            $this->assertCanonicalAuction($bid->auction);
            if (! SettlementChannel::isExternal((string)$bid->auction->settlement_channel)) {
                throw new RuntimeException('Bid is not an external-capital bid.');
            }

            $intent = ExternalPaymentIntent::query()
                ->whereKey($bid->external_payment_intent_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string)$intent->reference_type !== 'stock_bid'
                || (string)$intent->reference_id !== (string)$bid->id) {
                throw new RuntimeException('External payment intent does not belong to this bid.');
            }
            if ((string)$intent->status !== ExternalPaymentIntent::CONFIRMED) {
                throw new RuntimeException('External payment intent must be confirmed before bid activation.');
            }

            $bid->status = 'active';
            $bid->save();
            return $bid->fresh();
        });
    }

    public function cancelActiveBaharBid(Bid $bid, int $userId, string $cancellationKey): Bid
    {
        if (trim($cancellationKey) === '') throw new InvalidArgumentException('Bid cancellation key is required.');
        if ((int)$bid->user_id !== $userId) throw new RuntimeException('Bid does not belong to user.');
        if (! $bid->acceptance_key || ! $bid->reservation_key) throw new RuntimeException('Bid is not a canonical Active Bahar bid.');
        if ($bid->status === 'canceled') return $bid;
        if ($bid->status !== 'active') throw new RuntimeException('Only an active bid can be cancelled.');

        return DB::transaction(function () use ($bid,$cancellationKey) {
            $bid = Bid::query()->whereKey($bid->id)->lockForUpdate()->firstOrFail();
            if ($bid->status === 'canceled') return $bid;
            if ($bid->status !== 'active') throw new RuntimeException('Only an active bid can be cancelled.');
            $this->reservations->release((string)$bid->reservation_key, $cancellationKey);
            $bid->status='canceled'; $bid->save();
            return $bid->fresh();
        });
    }

    private function assertCanonicalAuction(Auction $auction): void
    {
        $auction->loadMissing('stock');
        $this->eligibility->assertAllowed(
            (string)($auction->stock?->issuer_type ?? ''),
            (string)($auction->market_type ?? ''),
            (string)($auction->supply_source ?? ''),
            (string)($auction->settlement_channel ?? '')
        );
        $this->pricing->assertCanonicalAuction($auction);
    }

    private function assertBidBounds(Auction $auction, int $priceGol, int $quantity): void
    {
        if ($priceGol <= 0 || $quantity <= 0) throw new InvalidArgumentException('Bid price and quantity must be positive integers.');
        if ($auction->min_bid_gol !== null && $priceGol < (int)$auction->min_bid_gol) throw new RuntimeException('Bid price below canonical minimum.');
        if ($auction->max_bid_gol !== null && $priceGol > (int)$auction->max_bid_gol) throw new RuntimeException('Bid price above canonical maximum.');
        if ($quantity > (int)$auction->lot_size) throw new RuntimeException('Bid quantity exceeds lot size.');
    }
}
