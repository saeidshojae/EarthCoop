<?php
namespace App\Modules\Stock\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class HoldingTransaction extends Model
{
    protected $table = 'holding_transactions';
    
    protected $fillable = [
        'holding_id',
        'type',
        'quantity',
        'ref_type',
        'ref_id',
        'meta',
        'description',
    ];
    
    protected $casts = [
        'quantity' => 'integer',
        'meta' => 'array',
    ];
    
    public function holding(): BelongsTo
    {
        return $this->belongsTo(Holding::class);
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
    
    public function scopeSettlement($query)
    {
        return $query->where('type', 'settlement');
    }
}
