<?php
namespace App\Modules\Stock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Holding extends Model
{
    protected $table = 'holdings';
    
    protected $fillable = [
        'user_id',
        'stock_id',
        'quantity',
    ];
    
    protected $casts = [
        'quantity' => 'integer',
    ];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
    
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }
    
    public function transactions(): HasMany
    {
        return $this->hasMany(HoldingTransaction::class);
    }
    
    public function getTotalValueAttribute(): float
    {
        return $this->quantity * $this->stock->base_share_price;
    }
}
