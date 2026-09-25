<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LocationStructureClaim extends Model
{
    protected $fillable = [
        'location_id', 'location_proposal_id', 'reference_settlement_id', 'claim_type', 'status', 'proposer_user_id',
        'reviewed_by_user_id', 'review_reason', 'approved_at', 'metadata', 'audit_log',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'metadata' => 'array',
        'audit_log' => 'array',
    ];

    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function locationProposal(): BelongsTo { return $this->belongsTo(LocationProposal::class, 'location_proposal_id'); }
    public function referenceSettlement(): BelongsTo { return $this->belongsTo(ReferenceSettlement::class, 'reference_settlement_id'); }
    public function proposer(): BelongsTo { return $this->belongsTo(User::class, 'proposer_user_id'); }
    public function evidence(): HasMany { return $this->hasMany(LocationStructureClaimEvidence::class); }
    public function groupRequests(): HasMany { return $this->hasMany(LocationScopedGroupRequest::class); }
}
