<?php

namespace App\Models;

use App\Enums\Elections\ElectionAcceptanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'election_id',
        'user_id',
        'position',
        'accept_status',
        'acceptance_status',
    ];

    protected $casts = [
        'acceptance_status' => ElectionAcceptanceStatus::class,
    ];

    /**
     * Compatibility bridge for the inconsistent legacy acceptance contract.
     *
     * Legacy 1 means an offer is pending, 2 means accepted, and 0 is only
     * provably declined when the canonical state was already pending. A bare 0
     * is therefore never guessed into a canonical decline.
     */
    public function setAcceptStatusAttribute(mixed $value): void
    {
        $this->attributes['accept_status'] = $value;

        $current = $this->attributes['acceptance_status'] ?? null;
        $raw = $value === null ? null : (string) $value;

        if ($raw === '1' || $raw === ElectionAcceptanceStatus::Pending->value) {
            $this->attributes['acceptance_status'] = ElectionAcceptanceStatus::Pending->value;
            return;
        }

        if ($raw === '2' || $raw === ElectionAcceptanceStatus::Accepted->value) {
            $this->attributes['acceptance_status'] = ElectionAcceptanceStatus::Accepted->value;
            return;
        }

        if ($raw === ElectionAcceptanceStatus::Declined->value) {
            $this->attributes['acceptance_status'] = ElectionAcceptanceStatus::Declined->value;
            return;
        }

        if ($raw === ElectionAcceptanceStatus::Expired->value) {
            $this->attributes['acceptance_status'] = ElectionAcceptanceStatus::Expired->value;
            return;
        }

        if ($raw === '0' && $current === ElectionAcceptanceStatus::Pending->value) {
            $this->attributes['acceptance_status'] = ElectionAcceptanceStatus::Declined->value;
        }
    }

    /**
     * Legacy relation only. Historical votes.candidate_id is overloaded and
     * often contains User.id. New election-domain code must use Vote::candidateUser().
     */
    public function votes()
    {
        return $this->hasMany(Vote::class, 'candidate_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function election()
    {
        return $this->belongsTo(Election::class);
    }
}
