<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationStructureClaimEvidence extends Model
{
    protected $table = 'location_structure_claim_evidence';
    protected $fillable = ['location_structure_claim_id', 'user_id', 'evidence'];
    protected $casts = ['evidence' => 'array'];

    public function claim(): BelongsTo { return $this->belongsTo(LocationStructureClaim::class, 'location_structure_claim_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
