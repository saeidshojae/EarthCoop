<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReferenceSettlementResidenceClaim extends Model
{
    protected $fillable = ['reference_settlement_id', 'user_id', 'status', 'submitted_at'];
    protected $casts = ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(ReferenceSettlement::class, 'reference_settlement_id');
    }

    public function claimant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
