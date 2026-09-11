<?php

namespace App\Models;

use App\Enums\LocationGovernance\LocationProposalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LocationProposal extends Model
{
    protected $fillable = [
        'proposer_user_id',
        'parent_location_id',
        'location_schema_id',
        'location_type_id',
        'country_code',
        'canonical_name',
        'normalized_name',
        'localized_names',
        'status',
        'resolved_location_id',
        'reviewed_by_user_id',
        'review_reason',
        'approved_at',
        'audit_log',
        'metadata',
    ];

    protected $casts = [
        'localized_names' => 'array',
        'status' => LocationProposalStatus::class,
        'approved_at' => 'datetime',
        'audit_log' => 'array',
        'metadata' => 'array',
    ];

    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposer_user_id');
    }

    public function parentLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'parent_location_id');
    }

    public function schema(): BelongsTo
    {
        return $this->belongsTo(LocationSchema::class, 'location_schema_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LocationType::class, 'location_type_id');
    }

    public function resolvedLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'resolved_location_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(LocationProposalEvidence::class);
    }
}
