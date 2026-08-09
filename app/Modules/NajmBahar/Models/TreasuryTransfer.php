<?php

namespace App\Modules\NajmBahar\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TreasuryTransfer extends Model
{
    protected $table = 'najm_bahar_treasury_transfers';

    protected $fillable = [
        'from_fund_id',
        'to_fund_id',
        'transaction_id',
        'authorized_by',
        'amount',
        'reason',
        'policy_reference',
        'idempotency_key',
        'meta',
    ];

    protected $casts = [
        'amount' => 'integer',
        'meta' => 'array',
    ];

    public function fromFund(): BelongsTo
    {
        return $this->belongsTo(TreasuryFund::class, 'from_fund_id');
    }

    public function toFund(): BelongsTo
    {
        return $this->belongsTo(TreasuryFund::class, 'to_fund_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
