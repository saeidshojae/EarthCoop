<?php
namespace App\Modules\Stock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stock extends Model
{
    protected $table = 'stocks';

    protected $fillable = [
        'issuer_type','issuer_id','startup_valuation','startup_valuation_gol','total_shares',
        'base_share_price','base_share_price_gol','available_shares','info',
    ];

    protected $casts = [
        'issuer_id'=>'integer','startup_valuation'=>'decimal:2','startup_valuation_gol'=>'integer',
        'base_share_price'=>'decimal:2','base_share_price_gol'=>'integer','total_shares'=>'integer','available_shares'=>'integer',
    ];

    public function auctions(): HasMany { return $this->hasMany(Auction::class); }
    public function holdings(): HasMany { return $this->hasMany(Holding::class); }

    public function getMarketCapAttribute(): float { return $this->total_shares * $this->base_share_price; }

    public function getCanonicalMarketCapGolAttribute(): ?int
    {
        return $this->startup_valuation_gol !== null ? (int)$this->startup_valuation_gol : null;
    }

    /** Legacy decimal recalculation remains transitional. Canonical pricing must use StockPricingService. */
    public function recalculateMarketData()
    {
        $sold=\App\Modules\Stock\Models\StockTransaction::whereHas('auction',fn($q)=>$q->where('stock_id',$this->id))->where('type','buy')->get();
        $soldShares=$sold->sum('shares_count'); $soldValue=$sold->sum(fn($t)=>$t->shares_count*$t->price);
        $bids=\App\Modules\Stock\Models\Bid::whereHas('auction',fn($q)=>$q->where('stock_id',$this->id))->whereIn('status',['active','won'])->get();
        $bidShares=$bids->sum('quantity'); $bidValue=$bids->sum(fn($b)=>$b->quantity*$b->price);
        $totalShares=$soldShares+$bidShares;
        if($totalShares>0){
            $oldPrice=$this->base_share_price; $oldValuation=$this->startup_valuation; $newPrice=($soldValue+$bidValue)/$totalShares;
            $this->base_share_price=$newPrice; $this->startup_valuation=$this->base_share_price*$this->total_shares; $this->save();
            if($oldPrice!==null&&abs($oldPrice-$newPrice)>0.01){ event(new \App\Events\StockPriceChanged($this,$oldPrice,$newPrice,$oldValuation,$this->startup_valuation)); }
        }
    }
}
