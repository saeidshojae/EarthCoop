<?php

namespace App\Modules\Stock\Services;

use App\Modules\NajmBahar\Services\ActiveBaharReservationService;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Bid;
use App\Modules\Stock\Models\HoldingReservation;
use App\Modules\Stock\Settlement\SettlementChannel;
use App\Modules\Stock\Settlement\SettlementEligibilityPolicy;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SecondaryAuctionCloseService
{
    public function __construct(
        private readonly SecondaryMarketSettlementService $settlements,
        private readonly ActiveBaharReservationService $moneyReservations,
        private readonly HoldingReservationService $shareReservations,
    ) {}

    public function close(Auction $auction): array
    {
        $auction->loadMissing('stock');
        if((string)$auction->market_type!==SettlementEligibilityPolicy::MARKET_SECONDARY
            || (string)$auction->supply_source!==SettlementEligibilityPolicy::SUPPLY_HOLDER
            || (string)$auction->settlement_channel!==SettlementChannel::ACTIVE_BAHAR) {
            throw new RuntimeException('Auction is not canonical secondary Active Bahar market.');
        }
        if(!$auction->hasCanonicalGolPricing()) throw new RuntimeException('Secondary canonical close requires Gol pricing.');
        if(!$auction->seller_user_id||!$auction->seller_holding_reservation_key) throw new RuntimeException('Secondary seller supply is not reserved.');

        return DB::transaction(function() use($auction){
            $auction=Auction::query()->whereKey($auction->id)->lockForUpdate()->firstOrFail();
            if($auction->status==='settled') return ['status'=>'settled','auction_id'=>$auction->id,'idempotent'=>true];
            if(!in_array((string)$auction->status,['running','settling'],true)) throw new RuntimeException('Secondary auction is not closable.');

            $legacy=Bid::query()->where('auction_id',$auction->id)->where('status','active')->where(function($q){
                $q->whereNull('price_gol')->orWhereNull('acceptance_key')->orWhereNull('reservation_key');
            })->count();
            if($legacy>0) throw new RuntimeException('Secondary close blocked by legacy/incomplete bids.');

            $shareReservation=HoldingReservation::query()->where('reservation_key',$auction->seller_holding_reservation_key)->lockForUpdate()->first();
            if(!$shareReservation||$shareReservation->status!==HoldingReservation::RESERVED) throw new RuntimeException('Seller share reservation is not active.');
            $capacity=(int)$shareReservation->quantity-(int)$shareReservation->settled_quantity-(int)$shareReservation->released_quantity;
            if($capacity<=0) throw new RuntimeException('No reserved seller shares are available.');

            $bids=Bid::query()->where('auction_id',$auction->id)->where('status','active')->whereNotNull('price_gol')->whereNotNull('acceptance_key')->whereNotNull('reservation_key')
                ->orderByDesc('price_gol')->orderBy('created_at')->orderBy('id')->lockForUpdate()->get();

            $auction->status='settling'; $auction->save();
            if($bids->isEmpty()){
                $this->shareReservations->release((string)$auction->seller_holding_reservation_key,'secondary:'.$auction->id.':no-bids');
                $auction->status='settled'; $auction->save();
                return ['status'=>'settled','auction_id'=>$auction->id,'winners'=>[],'allocated_shares'=>0,'clearing_price_gol'=>null];
            }

            $plan=$this->buildPlan($auction,$bids,min($capacity,(int)$auction->shares_count));
            $winnerIds=collect($plan['allocations'])->pluck('bid_id')->all();

            foreach($bids as $bid){
                if(in_array((int)$bid->id,$winnerIds,true)) continue;
                $this->moneyReservations->release((string)$bid->reservation_key,'secondary:'.$auction->id.':bid:'.$bid->id.':lost');
                $bid->status='lost'; $bid->save();
            }

            $settled=[];
            foreach($plan['allocations'] as $item){
                $bid=$bids->firstWhere('id',$item['bid_id']);
                $targetTotal=$item['price_gol']*$item['quantity'];
                $this->moneyReservations->reduce((string)$bid->reservation_key,$targetTotal,'secondary:'.$auction->id.':bid:'.$bid->id.':allocation',['settlement_price_gol'=>$item['price_gol'],'allocated_quantity'=>$item['quantity']]);
                if((int)$bid->price_gol!==$item['price_gol']){ $bid->price_gol=$item['price_gol']; $bid->save(); }
                $result=$this->settlements->settle($auction,$bid,$item['quantity'],'secondary:'.$auction->id.':bid:'.$bid->id.':settle');
                $settled[]=$result;
            }

            $freshReservation=HoldingReservation::query()->whereKey($shareReservation->id)->lockForUpdate()->firstOrFail();
            $remaining=(int)$freshReservation->quantity-(int)$freshReservation->settled_quantity-(int)$freshReservation->released_quantity;
            if($remaining>0){
                // Release the unsold remainder by closing the reservation; consumed quantity remains recorded.
                $freshReservation->forceFill([
                    'released_quantity'=>(int)$freshReservation->released_quantity+$remaining,
                    'status'=>HoldingReservation::RELEASED,
                    'released_at'=>now(),
                    'metadata'=>array_merge((array)$freshReservation->metadata,['close_release_key'=>'secondary:'.$auction->id.':unsold']),
                ])->save();
            }

            $auction->status='settled'; $auction->save();
            return ['status'=>'settled','auction_id'=>$auction->id,'winners'=>$settled,'allocated_shares'=>array_sum(array_column($plan['allocations'],'quantity')),'clearing_price_gol'=>$plan['clearing_price_gol']];
        });
    }

    private function buildPlan(Auction $auction,$bids,int $capacity): array
    {
        $allocations=[];$remaining=$capacity;$type=(string)$auction->type;
        if($type==='single_winner'){
            $bid=$bids->first();$qty=min((int)$bid->quantity,$remaining);
            if($qty>0)$allocations[]=['bid_id'=>$bid->id,'quantity'=>$qty,'price_gol'=>(int)$bid->price_gol];
            return ['allocations'=>$allocations,'clearing_price_gol'=>$allocations[0]['price_gol']??null];
        }
        if(!in_array($type,['uniform_price','pay_as_bid'],true)) throw new RuntimeException('Unsupported secondary auction type.');
        foreach($bids as $bid){ if($remaining<=0)break;$qty=min((int)$bid->quantity,$remaining);if($qty<=0)continue;$allocations[]=['bid_id'=>$bid->id,'quantity'=>$qty,'price_gol'=>(int)$bid->price_gol];$remaining-=$qty; }
        $clearing=$allocations!==[]?(int)end($allocations)['price_gol']:null;
        if($type==='uniform_price'&&$clearing!==null){ foreach($allocations as &$a)$a['price_gol']=$clearing; unset($a); }
        return ['allocations'=>$allocations,'clearing_price_gol'=>$clearing];
    }
}
