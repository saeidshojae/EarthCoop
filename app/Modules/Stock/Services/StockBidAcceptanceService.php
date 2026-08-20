<?php

namespace App\Modules\Stock\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Services\ActiveBaharReservationService;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Bid;
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

        $auction->loadMissing('stock');
        $this->eligibility->assertAllowed(
            (string)($auction->stock?->issuer_type ?? ''),
            (string)($auction->market_type ?? ''),
            (string)($auction->supply_source ?? ''),
            (string)($auction->settlement_channel ?? '')
        );
        $this->pricing->assertCanonicalAuction($auction);

        if ((string)$auction->settlement_channel !== SettlementChannel::ACTIVE_BAHAR) {
            throw new RuntimeException('This canonical bid acceptance path is Active Bahar only.');
        }

        if ((string)$auction->market_type === SettlementEligibilityPolicy::MARKET_SECONDARY
            && (string)$auction->settlement_channel !== SettlementChannel::ACTIVE_BAHAR) {
            throw new RuntimeException('Secondary-market bids require Active Bahar settlement.');
        }

        if ($priceGol <= 0 || $quantity <= 0) throw new InvalidArgumentException('Bid price and quantity must be positive integers.');
        if ($auction->min_bid_gol !== null && $priceGol < (int)$auction->min_bid_gol) throw new RuntimeException('Bid price below canonical minimum.');
        if ($auction->max_bid_gol !== null && $priceGol > (int)$auction->max_bid_gol) throw new RuntimeException('Bid price above canonical maximum.');
        if ($quantity > (int)$auction->lot_size) throw new RuntimeException('Bid quantity exceeds lot size.');

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
}
