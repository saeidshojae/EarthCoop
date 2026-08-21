<?php

namespace App\Models;

use App\Enums\Elections\ElectionResponsibilityOfferStatus;
use Illuminate\Database\Eloquent\Model;

class ElectionResponsibilityOffer extends Model
{
    protected $fillable = [
        'election_id', 'candidate_user_id', 'position', 'ranking_position',
        'contract_version_id', 'status', 'offered_at', 'expires_at', 'responded_at',
        'eligibility_checked_at', 'resolution_reason', 'response_metadata',
    ];

    protected $casts = [
        'ranking_position' => 'integer',
        'status' => ElectionResponsibilityOfferStatus::class,
        'offered_at' => 'datetime',
        'expires_at' => 'datetime',
        'responded_at' => 'datetime',
        'eligibility_checked_at' => 'datetime',
        'response_metadata' => 'array',
    ];

    public function election() { return $this->belongsTo(Election::class); }
    public function candidateUser() { return $this->belongsTo(User::class, 'candidate_user_id'); }
    public function contractVersion() { return $this->belongsTo(ElectionResponsibilityContractVersion::class, 'contract_version_id'); }
}
