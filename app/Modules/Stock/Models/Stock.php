<?php
namespace App\Modules\Stock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stock extends Model
{
    protected $table = 'stocks';
    
    protected $fillable = [
        'startup_valuation', // ارزش پایه استارتاپ
        'total_shares',      // تعداد کل سهام
        'base_share_price',  // ارزش پایه هر سهم
        'available_shares',  // تعداد سهام قابل عرضه
        'info',              // اطلاعات تکمیلی
    ];
    
    protected $casts = [
        'startup_valuation' => 'decimal:2',
        'base_share_price' => 'decimal:2',
        'total_shares' => 'integer',
        'available_shares' => 'integer',
    ];
    
    public function auctions(): HasMany
    {
        return $this->hasMany(Auction::class);
    }
    
    public function holdings(): HasMany
    {
        return $this->hasMany(Holding::class);
    }
    
    public function getMarketCapAttribute(): float
    {
        return $this->total_shares * $this->base_share_price;
    }

    /**
     * Recalculate base_share_price and startup_valuation based on
     * recent stock transactions and bids.
     */
    public function recalculateMarketData()
    {
        // sum of sold transactions (type = 'buy')
        $sold = \App\Modules\Stock\Models\StockTransaction::whereHas('auction', function($q){
            $q->where('stock_id', $this->id);
        })->where('type', 'buy')->get();

        $soldShares = $sold->sum('shares_count');
        $soldValue = $sold->sum(function($t){ return $t->shares_count * $t->price; });

        // include relevant bids (active or won) related to this stock
        $bids = \App\Modules\Stock\Models\Bid::whereHas('auction', function($q){
            $q->where('stock_id', $this->id);
        })->whereIn('status', ['active', 'won'])->get();

        $bidShares = $bids->sum('quantity');
        $bidValue = $bids->sum(function($b){ return $b->quantity * $b->price; });

        $totalShares = $soldShares + $bidShares;
        if ($totalShares > 0) {
            $oldPrice = $this->base_share_price;
            $oldValuation = $this->startup_valuation;
            $newPrice = ($soldValue + $bidValue) / $totalShares;
            $this->base_share_price = $newPrice;
            $this->startup_valuation = $this->base_share_price * $this->total_shares;
            $this->save();
            
            // Dispatch event if price changed
            if ($oldPrice !== null && abs($oldPrice - $newPrice) > 0.01) {
                event(new \App\Events\StockPriceChanged(
                    $this,
                    $oldPrice,
                    $newPrice,
                    $oldValuation,
                    $this->startup_valuation
                ));
            }
        }
    }
}
