<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationScopedGroupRequest extends Model
{
    protected $fillable = [
        'requester_user_id', 'location_id', 'location_proposal_id', 'location_structure_claim_id', 'reference_settlement_residence_claim_id',
        'scope_kind', 'dimension_key', 'dimension_value_key', 'status', 'group_id', 'governance_area_id', 'metadata',
    ];

    protected $casts = ['metadata' => 'array'];

    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requester_user_id'); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function locationProposal(): BelongsTo { return $this->belongsTo(LocationProposal::class); }
    public function locationStructureClaim(): BelongsTo { return $this->belongsTo(LocationStructureClaim::class); }
    public function referenceSettlementResidenceClaim(): BelongsTo { return $this->belongsTo(ReferenceSettlementResidenceClaim::class); }
    public function group(): BelongsTo { return $this->belongsTo(Group::class); }
    public function governanceArea(): BelongsTo { return $this->belongsTo(GovernanceArea::class); }
}
