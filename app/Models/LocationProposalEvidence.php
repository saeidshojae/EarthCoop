<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationProposalEvidence extends Model
{
    protected $table = 'location_proposal_evidence';

    protected $fillable = [
        'location_proposal_id',
        'user_id',
        'evidence',
    ];

    protected $casts = [
        'evidence' => 'array',
    ];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(LocationProposal::class, 'location_proposal_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
