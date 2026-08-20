<?php

namespace App\Modules\Stock\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\ActiveBaharReservation;
use App\Modules\NajmBahar\Services\ActiveBaharReservationService;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Bid;
use App\Modules\Stock\Models\Holding;
use App\Modules\Stock\Models\HoldingReservation;
use App\Modules\Stock\Models\HoldingTransaction;
use App\Modules\Stock\Settlement\SettlementChannel;
use App\Modules\Stock\Settlement\SettlementEligibilityPolicy;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SecondaryMarketSettlementService
{
    public function __construct(
        private readonly ActiveBaharReservationService $moneyReservations,
        private readonly HoldingReservationService $shareReservations,
        private readonly HoldingService $holdings,
    ) {}

    public function settle(Auction $auction,Bid $bid,int $quantity,string $settlementKey): array
    {
        if($quantity<=0) throw new RuntimeException('Secondary settlement quantity must be positive.');
        if(trim($settlementKey)==='') throw new RuntimeException('Secondary settlement key is required.');

        $auction->loadMissing('stock');
        if((string)$auction->market_type!==SettlementEligibilityPolicy::MARKET_SECONDARY
            || (string)$auction->supply_source!==SettlementEligibilityPolicy::SUPPLY_HOLDER
            || (string)$auction->settlement_channel!==SettlementChannel::ACTIVE_BAHAR) {
            throw new RuntimeException('Auction is not canonical secondary Active Bahar settlement.');
        }
        if(!$auction->seller_user_id || !$auction->seller_holding_reservation_key) throw new RuntimeException('Secondary auction seller supply is not reserved.');
        if((int)$bid->auction_id!==(int)$auction->id) throw new RuntimeException('Bid does not belong to this auction.');
        if((int)$bid->user_id===(int)$auction->seller_user_id) throw new RuntimeException('Seller cannot buy from self.');
        if(!$bid->reservation_key || (int)($bid->price_gol??0)<=0) throw new RuntimeException('Canonical buyer Active Bahar reservation is missing.');
        if($quantity>(int)$bid->quantity) throw new RuntimeException('Settlement quantity exceeds bid quantity.');

        return DB::transaction(function() use($auction,$bid,$quantity,$settlementKey){
            $auction=Auction::query()->whereKey($auction->id)->lockForUpdate()->firstOrFail();
            $bid=Bid::query()->whereKey($bid->id)->lockForUpdate()->firstOrFail();

            $existingSellerTx=HoldingTransaction::query()->where('idempotency_key',$settlementKey.':seller-asset')->lockForUpdate()->first();
            $existingBuyerTx=HoldingTransaction::query()->where('idempotency_key',$settlementKey.':buyer-asset')->lockForUpdate()->first();
            if($existingSellerTx&&$existingBuyerTx&&$bid->status==='won') {
                if((int)$existingSellerTx->quantity!==$quantity || (int)$existingBuyerTx->quantity!==$quantity) throw new RuntimeException('Secondary settlement key conflicts with existing settlement quantity.');
                return ['status'=>'settled','idempotent'=>true,'bid_id'=>$bid->id,'quantity'=>$quantity];
            }
            if($bid->status!=='active') throw new RuntimeException('Bid is not settleable with this settlement key.');

            $sellerAccount=Account::query()->where('user_id',$auction->seller_user_id)->where('type','user')->where('status',1)->orderBy('id')->lockForUpdate()->first();
            if(!$sellerAccount) throw new RuntimeException('Seller active Najm Bahar account not found.');

            $moneyReservation=ActiveBaharReservation::query()->where('reservation_key',$bid->reservation_key)->lockForUpdate()->first();
            if(!$moneyReservation || $moneyReservation->status!==ActiveBaharReservation::RESERVED) throw new RuntimeException('Buyer Active Bahar reservation is not settleable.');
            $expectedTotal=(int)$bid->price_gol*$quantity;
            if($expectedTotal<=0 || (int)$moneyReservation->amount<$expectedTotal) throw new RuntimeException('Buyer reservation does not cover settlement amount.');

            $shareReservation=HoldingReservation::query()->where('reservation_key',$auction->seller_holding_reservation_key)->lockForUpdate()->first();
            if(!$shareReservation || $shareReservation->status!==HoldingReservation::RESERVED) throw new RuntimeException('Seller share reservation is not settleable.');
            $remainingShares=(int)$shareReservation->quantity-(int)$shareReservation->settled_quantity-(int)$shareReservation->released_quantity;
            if($remainingShares<$quantity) throw new RuntimeException('Seller reserved shares do not cover settlement quantity.');

            $sellerHolding=Holding::query()->whereKey($shareReservation->holding_id)->lockForUpdate()->firstOrFail();
            if((int)$sellerHolding->quantity<$quantity) throw new RuntimeException('Seller holding no longer backs reserved shares.');

            if((int)$moneyReservation->amount>$expectedTotal) {
                $this->moneyReservations->reduce((string)$bid->reservation_key,$expectedTotal,$settlementKey.':money-adjust',['secondary_market'=>true]);
            }
            $this->moneyReservations->settle((string)$bid->reservation_key,(string)$sellerAccount->account_number,$settlementKey.':money',['auction_id'=>$auction->id,'bid_id'=>$bid->id,'secondary_market'=>true]);

            $sellerHolding->quantity=(int)$sellerHolding->quantity-$quantity;
            $sellerHolding->save();
            $sellerTx=HoldingTransaction::create([
                'idempotency_key'=>$settlementKey.':seller-asset','holding_id'=>$sellerHolding->id,'type'=>'debit','quantity'=>$quantity,
                'description'=>'Canonical secondary sale','ref_type'=>Auction::class,'ref_id'=>$auction->id,
                'meta'=>['bid_id'=>$bid->id,'price_gol'=>(int)$bid->price_gol,'secondary_market'=>true],
            ]);

            $buyerTx=$this->holdings->settlementIdempotent((int)$bid->user_id,(int)$auction->stock_id,$quantity,$settlementKey.':buyer-asset','Canonical secondary purchase',$auction,['bid_id'=>$bid->id,'price_gol'=>(int)$bid->price_gol,'secondary_market'=>true]);
            $this->shareReservations->consume((string)$auction->seller_holding_reservation_key,$quantity,$settlementKey.':seller-reservation');

            $bid->status='won'; $bid->save();

            return [
                'status'=>'settled','idempotent'=>false,'bid_id'=>$bid->id,'quantity'=>$quantity,
                'seller_user_id'=>(int)$auction->seller_user_id,'buyer_user_id'=>(int)$bid->user_id,
                'seller_holding_transaction_id'=>$sellerTx->id,'buyer_holding_transaction_id'=>$buyerTx->id,
                'total_gol'=>$expectedTotal,
            ];
        });
    }
}
