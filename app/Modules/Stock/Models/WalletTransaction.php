<?php
namespace App\Modules\Stock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WalletTransaction extends Model
{
    protected $table = 'wallet_transactions';
    
    protected $fillable = [
        'wallet_id',
        'type',
        'amount',
        'ref_type',
        'ref_id',
        'meta',
        'description',
    ];
    
    protected $casts = [
        'amount' => 'decimal:2',
        'meta' => 'array',
    ];
    
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
    
    public function reference(): MorphTo
    {
        return $this->morphTo('ref');
    }
    
    public function scopeCredit($query)
    {
        return $query->where('type', 'credit');
    }
    
    public function scopeDebit($query)
    {
        return $query->where('type', 'debit');
    }
    
    public function scopeHold($query)
    {
        return $query->where('type', 'hold');
    }
    
    public function scopeRelease($query)
    {
        return $query->where('type', 'release');
    }
}
