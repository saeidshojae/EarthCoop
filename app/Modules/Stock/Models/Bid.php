<?php
namespace App\Modules\Stock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bid extends Model
{
    protected $table = 'bids';
    
    protected $fillable = [
        'auction_id',
        'user_id',
        'price',
        'quantity',
        'status',
    ];
    
    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
    ];
    
    // Support legacy/multiple migration schemas where columns may be named
    // 'price' or 'bid_price', and 'quantity' or 'shares_count'.
    protected function hasColumn(string $col): bool
    {
        try {
            return \Schema::hasColumn($this->getTable(), $col);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getPriceAttribute($value)
    {
        if ($value !== null) return $value;
        if ($this->hasColumn('bid_price') && array_key_exists('bid_price', $this->attributes)) {
            return $this->attributes['bid_price'];
        }
        return null;
    }

    public function setPriceAttribute($value)
    {
        if ($this->hasColumn('price')) {
            $this->attributes['price'] = $value;
        } elseif ($this->hasColumn('bid_price')) {
            $this->attributes['bid_price'] = $value;
        } else {
            $this->attributes['price'] = $value; // best effort
        }
    }

    public function getQuantityAttribute($value)
    {
        if ($value !== null) return $value;
        if ($this->hasColumn('shares_count') && array_key_exists('shares_count', $this->attributes)) {
            return (int) $this->attributes['shares_count'];
        }
        return null;
    }

    public function setQuantityAttribute($value)
    {
        if ($this->hasColumn('quantity')) {
            $this->attributes['quantity'] = $value;
        } elseif ($this->hasColumn('shares_count')) {
            $this->attributes['shares_count'] = $value;
        } else {
            $this->attributes['quantity'] = $value; // best effort
        }
    }
    
    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
    
    public function getTotalValueAttribute(): float
    {
        return $this->price * $this->quantity;
    }
    
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
    
    public function scopeWon($query)
    {
        return $query->where('status', 'won');
    }
    
    public function scopeLost($query)
    {
        return $query->where('status', 'lost');
    }
    
    public function scopeByPrice($query, $direction = 'desc')
    {
        try {
            if (\Schema::hasColumn($this->getTable(), 'price')) {
                return $query->orderBy('price', $direction);
            }
            if (\Schema::hasColumn($this->getTable(), 'bid_price')) {
                return $query->orderBy('bid_price', $direction);
            }
        } catch (\Exception $e) {
            // ignore schema detection errors and fall back to query unchanged
        }
        return $query;
    }
}