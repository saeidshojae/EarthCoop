<?php

namespace App\Modules\Stock\Services;

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

        // Slice 6 constitutional gate: a secondary-market order cannot even be
        // accepted unless its settlement rail is Active Bahar.
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

            // Reservation is inside the same DB transaction. If Bid creation
            // fails, the reservation insert rolls back with it.
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
                // Legacy non-null column retained only for schema compatibility;
                // canonical economic meaning is exclusively price_gol.
                'price'=>0,
                'price_gol'=>$priceGol,
                'reservation_key'=>$reservationKey,
                'quantity'=>$quantity,
                'status'=>'active',
            ]);
        });
    }
}
