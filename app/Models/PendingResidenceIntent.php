<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingResidenceIntent extends Model
{
    protected $fillable = [
        'user_id',
        'anchor_relationship_id',
        'location_proposal_id',
        'reference_settlement_residence_claim_id',
        'resolved_location_id',
        'status',
        'selected_at',
        'resolved_at',
        'cancelled_at',
        'metadata',
    ];

    protected $casts = [
        'selected_at' => 'datetime',
        'resolved_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function anchorRelationship(): BelongsTo
    {
        return $this->belongsTo(UserLocationRelationship::class, 'anchor_relationship_id');
    }

    public function locationProposal(): BelongsTo
    {
        return $this->belongsTo(LocationProposal::class);
    }

    public function referenceSettlementResidenceClaim(): BelongsTo
    {
        return $this->belongsTo(ReferenceSettlementResidenceClaim::class);
    }

    public function resolvedLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'resolved_location_id');
    }
}
