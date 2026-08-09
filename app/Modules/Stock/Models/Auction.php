<?php
namespace App\Modules\Stock\Models;

use App\Modules\Stock\Settlement\SettlementEligibilityPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Auction extends Model
{
    protected $table = 'auctions';
    
    protected $fillable = [
        'stock_id',
        'market_type',
        'supply_source',
        'settlement_channel',
        'quote_unit',
        'shares_count',
        'base_price',
        'start_time',
        'end_time',
        'ends_at',
        'status',
        'type',
        'settlement_mode',
        'min_bid',
        'max_bid',
        'lot_size',
        'channel_id',
        'info',
    ];
    
    protected $casts = [
        'base_price' => 'decimal:2',
        'min_bid' => 'decimal:2',
        'max_bid' => 'decimal:2',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'ends_at' => 'datetime',
        'shares_count' => 'integer',
        'lot_size' => 'integer',
    ];
    
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }
    
    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }
    
    public function activeBids(): HasMany
    {
        return $this->hasMany(Bid::class)->where('status', 'active');
    }

    /**
     * Fail closed unless issuer/market/supply/channel are explicitly
     * classified and permitted by the Stock × Najm Bahar boundary.
     */
    public function assertSettlementEligible(?SettlementEligibilityPolicy $policy = null): void
    {
        $policy ??= app(SettlementEligibilityPolicy::class);

        $policy->assertAllowed(
            (string) ($this->stock?->issuer_type ?? ''),
            (string) ($this->market_type ?? ''),
            (string) ($this->supply_source ?? ''),
            (string) ($this->settlement_channel ?? ''),
        );
    }
    
    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }
    
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }
    
    public function scopeSettled($query)
    {
        return $query->where('status', 'settled');
    }
    
    public function isActive(): bool
    {
        return $this->status === 'running' && 
               $this->ends_at && 
               $this->ends_at->isFuture();
    }
    
    public function isExpired(): bool
    {
        return $this->ends_at && $this->ends_at->isPast();
    }
}
