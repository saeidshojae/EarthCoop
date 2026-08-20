<?php

namespace App\Models;

use App\Modules\NajmBahar\Models\Account;
use App\Modules\NajmBahar\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FounderNajmBaharTransactionIntent extends Model
{
    protected $fillable = [
        'from_account_id','to_account_id','requested_by_user_id','approved_by_user_id','transaction_id',
        'amount','balance_type','transaction_type','intent_key','idempotency_key','status',
        'description','metadata','approved_at','executed_at','rejected_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'metadata' => 'array',
        'approved_at' => 'datetime',
        'executed_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function fromAccount(): BelongsTo { return $this->belongsTo(Account::class, 'from_account_id'); }
    public function toAccount(): BelongsTo { return $this->belongsTo(Account::class, 'to_account_id'); }
    public function requestedBy(): BelongsTo { return $this->belongsTo(User::class, 'requested_by_user_id'); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by_user_id'); }
    public function transaction(): BelongsTo { return $this->belongsTo(Transaction::class, 'transaction_id'); }
}
