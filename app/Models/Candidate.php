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
