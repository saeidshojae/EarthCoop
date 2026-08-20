<?php

namespace App\Modules\Stock\Services;

use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Settlement\SettlementChannel;
use App\Modules\Stock\Settlement\SettlementEligibilityPolicy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class SecondaryAuctionSupplyService
{
    public function __construct(private readonly HoldingReservationService $reservations) {}

    public function attachSellerSupply(Auction $auction,int $sellerUserId,int $quantity,string $reservationKey): Auction
    {
        if($sellerUserId<=0||$quantity<=0) throw new InvalidArgumentException('Seller and quantity must be positive.');
        $auction->loadMissing('stock');
        if((string)$auction->market_type!==SettlementEligibilityPolicy::MARKET_SECONDARY) throw new RuntimeException('Auction is not secondary market.');
        if((string)$auction->supply_source!==SettlementEligibilityPolicy::SUPPLY_HOLDER) throw new RuntimeException('Secondary auction must use holder supply.');
        if((string)$auction->settlement_channel!==SettlementChannel::ACTIVE_BAHAR) throw new RuntimeException('Secondary auction must settle in Active Bahar.');
        if((int)$auction->shares_count!==$quantity) throw new RuntimeException('Seller reservation quantity must match auction shares_count.');

        return DB::transaction(function() use($auction,$sellerUserId,$quantity,$reservationKey){
            $auction=Auction::query()->whereKey($auction->id)->lockForUpdate()->firstOrFail();
            if($auction->seller_holding_reservation_key){
                if((int)$auction->seller_user_id!==$sellerUserId || (string)$auction->seller_holding_reservation_key!==$reservationKey) throw new RuntimeException('Auction already has different seller supply attached.');
                return $auction;
            }
            $this->reservations->reserve($sellerUserId,(int)$auction->stock_id,(int)$auction->id,$quantity,$reservationKey,['market'=>'secondary']);
            $auction->seller_user_id=$sellerUserId;
            $auction->seller_holding_reservation_key=$reservationKey;
            $auction->save();
            return $auction->fresh();
        });
    }

    public function releaseSellerSupply(Auction $auction,string $releaseKey): Auction
    {
        if(!$auction->seller_holding_reservation_key) throw new RuntimeException('Auction has no seller supply reservation.');
        DB::transaction(function() use($auction,$releaseKey){
            $this->reservations->release((string)$auction->seller_holding_reservation_key,$releaseKey);
        });
        return $auction->fresh();
    }
}
