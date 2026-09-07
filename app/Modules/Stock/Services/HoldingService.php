<?php
namespace App\Modules\Stock\Services;

use App\Modules\Stock\Models\Holding;
use App\Modules\Stock\Models\HoldingTransaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class HoldingService
{
    public function getOrCreateHolding(int $userId,int $stockId): Holding
    {
        return Holding::firstOrCreate(['user_id'=>$userId,'stock_id'=>$stockId],['quantity'=>0]);
    }

    public function credit(Holding $holding,int $quantity,string $description=null,$reference=null): HoldingTransaction
    {
        return DB::transaction(function () use ($holding,$quantity,$description,$reference) {
            $holding->increment('quantity',$quantity);
            return $holding->transactions()->create(['type'=>'credit','quantity'=>$quantity,'description'=>$description,'ref_type'=>$reference?get_class($reference):null,'ref_id'=>$reference?->id]);
        });
    }

    public function debit(Holding $holding,int $quantity,string $description=null,$reference=null): HoldingTransaction
    {
        return DB::transaction(function () use ($holding,$quantity,$description,$reference) {
            if($holding->quantity<$quantity) throw new RuntimeException('Insufficient holdings');
            $holding->decrement('quantity',$quantity);
            return $holding->transactions()->create(['type'=>'debit','quantity'=>$quantity,'description'=>$description,'ref_type'=>$reference?get_class($reference):null,'ref_id'=>$reference?->id]);
        });
    }

    public function settlement(Holding $holding,int $quantity,string $description=null,$reference=null): HoldingTransaction
    {
        return DB::transaction(function () use ($holding,$quantity,$description,$reference) {
            $holding->increment('quantity',$quantity);
            return $holding->transactions()->create(['type'=>'settlement','quantity'=>$quantity,'description'=>$description,'ref_type'=>$reference?get_class($reference):null,'ref_id'=>$reference?->id]);
        });
    }

    public function settlementIdempotent(int $userId,int $stockId,int $quantity,string $idempotencyKey,string $description=null,$reference=null,array $meta=[]): HoldingTransaction
    {
        if($quantity<=0) throw new InvalidArgumentException('Holding settlement quantity must be positive.');
        if(trim($idempotencyKey)==='') throw new InvalidArgumentException('Holding settlement idempotency key is required.');

        return DB::transaction(function () use ($userId,$stockId,$quantity,$idempotencyKey,$description,$reference,$meta) {
            $existing=HoldingTransaction::query()->where('idempotency_key',$idempotencyKey)->lockForUpdate()->first();
            if($existing){
                $existing->loadMissing('holding');
                if((int)$existing->holding?->user_id!==$userId||(int)$existing->holding?->stock_id!==$stockId||(int)$existing->quantity!==$quantity||$existing->type!=='settlement') throw new RuntimeException('Holding settlement idempotency key conflicts with existing transaction.');
                return $existing;
            }

            $holding=Holding::firstOrCreate(['user_id'=>$userId,'stock_id'=>$stockId],['quantity'=>0]);
            $holding=Holding::query()->whereKey($holding->id)->lockForUpdate()->firstOrFail();
            $holding->quantity=(int)$holding->quantity+$quantity; $holding->save();

            return HoldingTransaction::create([
                'idempotency_key'=>$idempotencyKey,'holding_id'=>$holding->id,'type'=>'settlement','quantity'=>$quantity,
                'description'=>$description,'ref_type'=>$reference?get_class($reference):null,'ref_id'=>$reference?->id,'meta'=>$meta,
            ]);
        });
    }

    public function getQuantity(int $userId,int $stockId): int { return $this->getOrCreateHolding($userId,$stockId)->quantity; }
    public function getUserHoldings(int $userId){ return Holding::where('user_id',$userId)->with('stock')->where('quantity','>',0)->get(); }
}
