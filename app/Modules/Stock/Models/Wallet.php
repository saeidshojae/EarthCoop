<?php
namespace App\Modules\Stock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $table = 'wallets';
    
    protected $fillable = [
        'user_id',
        'balance',
        'held_amount',
        'currency',
    ];
    
    protected $casts = [
        'balance' => 'decimal:2',
        'held_amount' => 'decimal:2',
    ];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
    
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }
    
    public function getAvailableBalanceAttribute(): float
    {
        return $this->balance - $this->held_amount;
    }
    
    public function canAfford(float $amount): bool
    {
        return $this->available_balance >= $amount;
    }
}
