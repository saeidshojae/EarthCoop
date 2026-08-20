<?php

namespace App\Modules\Stock\Services;

use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Holding;
use App\Modules\Stock\Settlement\SettlementChannel;
use App\Modules\Stock\Settlement\SettlementEligibilityPolicy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class SecondaryListingService
{
    public function __construct(
        private readonly SecondaryAuctionSupplyService $supply,
        private readonly HoldingReservationService $reservations,
    ) {}

    public function create(
        int $sellerUserId,
        int $stockId,
        int $quantity,
        int $basePriceGol,
        ?int $minBidGol,
        ?int $maxBidGol,
        string $auctionType,
        \DateTimeInterface $endsAt,
        string $listingKey,
        ?string $info = null,
    ): Auction {
        if (! config('stock.secondary_market_enabled', false)) throw new RuntimeException('Secondary market is not enabled.');
        if ($sellerUserId <= 0 || $stockId <= 0 || $quantity <= 0 || $basePriceGol <= 0) throw new InvalidArgumentException('Seller, stock, quantity and base price must be positive.');
        if (trim($listingKey) === '') throw new InvalidArgumentException('Listing key is required.');
        if (! in_array($auctionType, ['single_winner','uniform_price','pay_as_bid'], true)) throw new InvalidArgumentException('Unsupported secondary auction type.');
        if ($endsAt <= now()) throw new InvalidArgumentException('Secondary auction end time must be in the future.');
        if ($minBidGol !== null && $minBidGol <= 0) throw new InvalidArgumentException('Minimum bid must be positive Gol.');
        if ($maxBidGol !== null && $maxBidGol <= 0) throw new InvalidArgumentException('Maximum bid must be positive Gol.');
        if ($minBidGol !== null && $maxBidGol !== null && $minBidGol > $maxBidGol) throw new InvalidArgumentException('Minimum bid cannot exceed maximum bid.');

        return DB::transaction(function () use ($sellerUserId,$stockId,$quantity,$basePriceGol,$minBidGol,$maxBidGol,$auctionType,$endsAt,$listingKey,$info) {
            $existing = Auction::query()->where('listing_key',$listingKey)->lockForUpdate()->first();
            if ($existing) {
                if ((int)$existing->seller_user_id !== $sellerUserId || (int)$existing->stock_id !== $stockId || (int)$existing->shares_count !== $quantity || (int)$existing->base_price_gol !== $basePriceGol) {
                    throw new RuntimeException('Secondary listing key conflicts with an existing auction.');
                }
                return $existing;
            }

            $holding = Holding::query()->where('user_id',$sellerUserId)->where('stock_id',$stockId)->lockForUpdate()->first();
            if (! $holding || (int)$holding->quantity <= 0) throw new RuntimeException('Seller has no holding for this stock.');
            if ($this->reservations->availableQuantity($holding) < $quantity) throw new RuntimeException('Insufficient available shares for listing.');

            $auction = Auction::query()->create([
                'stock_id'=>$stockId,'listing_key'=>$listingKey,
                'market_type'=>SettlementEligibilityPolicy::MARKET_SECONDARY,
                'supply_source'=>SettlementEligibilityPolicy::SUPPLY_HOLDER,
                'settlement_channel'=>SettlementChannel::ACTIVE_BAHAR,
                'quote_unit'=>'gol','shares_count'=>$quantity,
                'base_price'=>0,'base_price_gol'=>$basePriceGol,
                'min_bid'=>0,'min_bid_gol'=>$minBidGol,
                'max_bid'=>0,'max_bid_gol'=>$maxBidGol,
                'lot_size'=>$quantity,'start_time'=>now(),'ends_at'=>$endsAt,
                'status'=>'running','type'=>$auctionType,'info'=>$info,
            ]);

            return $this->supply->attachSellerSupply($auction,$sellerUserId,$quantity,'secondary:listing:'.$listingKey.':shares');
        });
    }

    public function cancel(Auction $auction,int $sellerUserId,string $cancelKey): Auction
    {
        if ((int)$auction->seller_user_id !== $sellerUserId) throw new RuntimeException('Auction does not belong to seller.');
        if ((string)$auction->market_type !== SettlementEligibilityPolicy::MARKET_SECONDARY) throw new RuntimeException('Auction is not secondary market.');
        if (! in_array((string)$auction->status,['running','scheduled'],true)) throw new RuntimeException('Secondary auction cannot be cancelled in current state.');
        if ($auction->activeBids()->exists()) throw new RuntimeException('Secondary auction with active bids cannot be cancelled directly.');

        return DB::transaction(function () use ($auction,$cancelKey) {
            $auction=Auction::query()->whereKey($auction->id)->lockForUpdate()->firstOrFail();
            $this->supply->releaseSellerSupply($auction,$cancelKey.':shares');
            $auction->status='cancelled'; $auction->save();
            return $auction->fresh();
        });
    }
}
