<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReferenceSettlementReview extends Model
{
    protected $fillable = [
        'reference_settlement_id', 'reviewed_by_user_id', 'decision', 'reason',
        'evidence_source', 'evidence_date', 'evidence_reference', 'snapshot',
    ];

    protected $casts = ['evidence_date' => 'date', 'snapshot' => 'array'];

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(ReferenceSettlement::class, 'reference_settlement_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
