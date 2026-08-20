<?php

namespace App\Modules\Stock\Services;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Services\ActiveBaharReservationService;
use App\Modules\Stock\Models\Auction;
use App\Modules\Stock\Models\Bid;
use App\Modules\Stock\Models\Stock;
use App\Modules\Stock\Settlement\SettlementChannel;
use App\Modules\Stock\Settlement\SettlementEligibilityPolicy;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CanonicalAuctionCloseService
{
    public function __construct(
        private readonly StockAtomicSettlementService $settlements,
        private readonly ActiveBaharReservationService $reservations,
    ) {}

    public function close(Auction $auction): array
    {
        $auction->loadMissing('stock');
        $auction->assertSettlementEligible();

        if (! $auction->hasCanonicalGolPricing()) throw new RuntimeException('Canonical close requires Gol pricing.');
        if ((string)$auction->settlement_channel !== SettlementChannel::ACTIVE_BAHAR) throw new RuntimeException('Canonical close currently supports Active Bahar only.');
        if ((string)$auction->market_type !== SettlementEligibilityPolicy::MARKET_PRIMARY || (string)$auction->supply_source !== SettlementEligibilityPolicy::SUPPLY_TREASURY) {
            throw new RuntimeException('Canonical close currently supports primary treasury supply only.');
        }

        $payeeAccountNumber=(string)config('stock.earthcoop_capital_account_number','');
        if($payeeAccountNumber==='') throw new RuntimeException('EarthCoop capital Najm Bahar account is not configured.');
        if(!Account::query()->where('account_number',$payeeAccountNumber)->where('status',1)->exists()) throw new RuntimeException('Configured EarthCoop capital Najm Bahar account does not exist or is inactive.');

        return DB::transaction(function () use ($auction,$payeeAccountNumber) {
            $auction=Auction::query()->whereKey($auction->id)->lockForUpdate()->firstOrFail();
            if($auction->status==='settled') return ['status'=>'settled','auction_id'=>$auction->id,'idempotent'=>true,'allocated_shares'=>0];
            if(!in_array((string)$auction->status,['running','settling'],true)) throw new RuntimeException('Auction is not in a canonical closable state.');

            $legacyActive=Bid::query()->where('auction_id',$auction->id)->where('status','active')->where(function($q){
                $q->whereNull('price_gol')->orWhereNull('acceptance_key')->orWhereNull('reservation_key');
            })->lockForUpdate()->count();
            if($legacyActive>0) throw new RuntimeException('Canonical close blocked: active legacy/incomplete bids exist.');

            $stock=Stock::query()->whereKey($auction->stock_id)->lockForUpdate()->firstOrFail();
            $capacity=min((int)$auction->shares_count,(int)$stock->available_shares);
            if($capacity<=0) throw new RuntimeException('No treasury shares are available for canonical settlement.');

            $bids=Bid::query()->where('auction_id',$auction->id)->where('status','active')->whereNotNull('price_gol')->whereNotNull('acceptance_key')->whereNotNull('reservation_key')
                ->orderByDesc('price_gol')->orderBy('created_at')->orderBy('id')->lockForUpdate()->get();

            $auction->status='settling'; $auction->save();
            if($bids->isEmpty()){
                $auction->status='settled'; $auction->save();
                return ['status'=>'settled','auction_id'=>$auction->id,'winners'=>[],'allocated_shares'=>0,'clearing_price_gol'=>null];
            }

            $plan=$this->buildPlan($auction,$bids,$capacity);
            $winnerIds=collect($plan['allocations'])->pluck('bid_id')->all();

            foreach($bids as $bid){
                if(in_array((int)$bid->id,$winnerIds,true)) continue;
                $this->reservations->release((string)$bid->reservation_key,'auction:'.$auction->id.':bid:'.$bid->id.':lost');
                $bid->status='lost'; $bid->save();
            }

            $settled=[];
            foreach($plan['allocations'] as $item){
                /** @var Bid $bid */
                $bid=$bids->firstWhere('id',$item['bid_id']);
                $targetTotal=$item['price_gol']*$item['quantity'];
                $this->reservations->reduce((string)$bid->reservation_key,$targetTotal,'auction:'.$auction->id.':bid:'.$bid->id.':allocation',['allocated_quantity'=>$item['quantity'],'settlement_price_gol'=>$item['price_gol']]);

                $allocation=$this->settlements->prepare($auction,$bid,$item['quantity'],'auction:'.$auction->id.':bid:'.$bid->id.':allocation',(string)$bid->reservation_key,$payeeAccountNumber,null,['auction_type'=>$auction->type],$item['price_gol']);
                $allocation=$this->settlements->settle($allocation);
                $settled[]=['allocation_id'=>$allocation->id,'bid_id'=>$bid->id,'user_id'=>$bid->user_id,'quantity'=>$item['quantity'],'price_gol'=>$item['price_gol'],'total_gol'=>$targetTotal];
            }

            $auction->status='settled'; $auction->save();
            return ['status'=>'settled','auction_id'=>$auction->id,'winners'=>$settled,'allocated_shares'=>array_sum(array_column($settled,'quantity')),'clearing_price_gol'=>$plan['clearing_price_gol']];
        });
    }

    private function buildPlan(Auction $auction,$bids,int $capacity): array
    {
        $allocations=[]; $remaining=$capacity; $type=(string)$auction->type;
        if($type==='single_winner'){
            $bid=$bids->first(); $qty=min((int)$bid->quantity,$remaining);
            if($qty>0)$allocations[]=['bid_id'=>$bid->id,'quantity'=>$qty,'price_gol'=>(int)$bid->price_gol];
            return ['allocations'=>$allocations,'clearing_price_gol'=>$allocations[0]['price_gol']??null];
        }
        if(!in_array($type,['uniform_price','pay_as_bid'],true)) throw new RuntimeException('Unsupported canonical auction type.');
        foreach($bids as $bid){
            if($remaining<=0)break; $qty=min((int)$bid->quantity,$remaining); if($qty<=0)continue;
            $allocations[]=['bid_id'=>$bid->id,'quantity'=>$qty,'price_gol'=>(int)$bid->price_gol]; $remaining-=$qty;
        }
        $clearing=$allocations!==[]?(int)end($allocations)['price_gol']:null;
        if($type==='uniform_price'&&$clearing!==null){ foreach($allocations as &$allocation)$allocation['price_gol']=$clearing; unset($allocation); }
        return ['allocations'=>$allocations,'clearing_price_gol'=>$clearing];
    }
}
