<?php

namespace App\Modules\Stock\Services;

use App\Modules\Stock\Models\Holding;
use App\Modules\Stock\Models\HoldingReservation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class HoldingReservationService
{
    public function reserve(int $sellerUserId,int $stockId,int $auctionId,int $quantity,string $reservationKey,array $metadata=[]): HoldingReservation
    {
        if($sellerUserId<=0||$stockId<=0||$auctionId<=0||$quantity<=0) throw new InvalidArgumentException('Seller, stock, auction and quantity must be positive.');
        if(trim($reservationKey)==='') throw new InvalidArgumentException('Holding reservation key is required.');

        return DB::transaction(function() use($sellerUserId,$stockId,$auctionId,$quantity,$reservationKey,$metadata){
            $existing=HoldingReservation::query()->where('reservation_key',$reservationKey)->lockForUpdate()->first();
            if($existing){
                $existing->loadMissing('holding');
                if((int)$existing->seller_user_id!==$sellerUserId||(int)$existing->holding?->stock_id!==$stockId||(int)$existing->auction_id!==$auctionId||(int)$existing->quantity!==$quantity) throw new RuntimeException('Holding reservation idempotency key conflicts with existing reservation.');
                return $existing;
            }
            $holding=Holding::query()->where('user_id',$sellerUserId)->where('stock_id',$stockId)->lockForUpdate()->first();
            if(!$holding) throw new RuntimeException('Seller holding not found.');
            $available=$this->availableQuantity($holding);
            if($available<$quantity) throw new RuntimeException('Insufficient seller shares available for reservation.');
            return HoldingReservation::create([
                'holding_id'=>$holding->id,'seller_user_id'=>$sellerUserId,'auction_id'=>$auctionId,'quantity'=>$quantity,
                'status'=>HoldingReservation::RESERVED,'reservation_key'=>$reservationKey,'metadata'=>$metadata,'reserved_at'=>now(),
            ]);
        });
    }

    public function release(string $reservationKey,string $releaseKey): HoldingReservation
    {
        if(trim($releaseKey)==='') throw new InvalidArgumentException('Release key is required.');
        return DB::transaction(function() use($reservationKey,$releaseKey){
            $r=$this->locked($reservationKey);
            if($r->status===HoldingReservation::RELEASED) return $r;
            if($r->status!==HoldingReservation::RESERVED) throw new RuntimeException('Only an active seller reservation can be released.');
            $meta=array_merge((array)$r->metadata,['release_key'=>$releaseKey]);
            $r->forceFill(['status'=>HoldingReservation::RELEASED,'released_quantity'=>(int)$r->quantity,'released_at'=>now(),'metadata'=>$meta])->save();
            return $r->fresh();
        });
    }

    public function consume(string $reservationKey,int $quantity,string $consumeKey): HoldingReservation
    {
        if($quantity<=0) throw new InvalidArgumentException('Consume quantity must be positive.');
        if(trim($consumeKey)==='') throw new InvalidArgumentException('Consume key is required.');
        return DB::transaction(function() use($reservationKey,$quantity,$consumeKey){
            $r=$this->locked($reservationKey);
            $keys=(array)data_get($r->metadata,'consume_keys',[]);
            if(isset($keys[$consumeKey])) return $r;
            if($r->status!==HoldingReservation::RESERVED) throw new RuntimeException('Seller reservation is not consumable.');
            $remaining=(int)$r->quantity-(int)$r->settled_quantity-(int)$r->released_quantity;
            if($quantity>$remaining) throw new RuntimeException('Seller reservation consume exceeds remaining quantity.');
            $keys[$consumeKey]=$quantity;
            $settled=(int)$r->settled_quantity+$quantity;
            $status=$settled===(int)$r->quantity?HoldingReservation::SETTLED:HoldingReservation::RESERVED;
            $r->forceFill(['settled_quantity'=>$settled,'status'=>$status,'settled_at'=>$status===HoldingReservation::SETTLED?now():$r->settled_at,'metadata'=>array_merge((array)$r->metadata,['consume_keys'=>$keys])])->save();
            return $r->fresh();
        });
    }

    public function availableQuantity(Holding $holding): int
    {
        $reserved=(int)HoldingReservation::query()->where('holding_id',$holding->id)->where('status',HoldingReservation::RESERVED)->sum(DB::raw('quantity - settled_quantity - released_quantity'));
        return max(0,(int)$holding->quantity-$reserved);
    }

    protected function locked(string $key): HoldingReservation
    {
        $r=HoldingReservation::query()->where('reservation_key',$key)->lockForUpdate()->first();
        if(!$r) throw new RuntimeException('Seller holding reservation not found.');
        return $r;
    }
}
