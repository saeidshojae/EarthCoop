<?php

namespace App\Models;

use App\Enums\Elections\ElectionVoteVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
    use HasFactory;

    protected $fillable = [
        'voter_id',
        'election_id',
        'candidate_id',
        'candidate_user_id',
        'position',
        'vote_visibility',
    ];

    protected $casts = [
        'vote_visibility' => ElectionVoteVisibility::class,
    ];

    protected static function booted(): void
    {
        // `votes` is the mutable current projection of the systemic ballot.
        // Canonical ballot changes are performed by ElectionBallotService via a
        // transactional query-level replacement *after* immutable audit events
        // are appended. Any ad-hoc model deletion (for example from profile or
        // membership maintenance code) would erase the projection without an
        // audit event and can corrupt an open ballot or historical evidence.
        // Returning false keeps such legacy direct deletes fail-closed while the
        // canonical query-level projection replacement remains unaffected.
        static::deleting(fn (self $vote): bool => false);
    }

    public function candidate()
    {
        return $this->belongsTo(Candidate::class, 'candidate_id');
    }

    public function candidateUser()
    {
        return $this->belongsTo(User::class, 'candidate_user_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'candidate_id');
    }

    public function election()
    {
        return $this->belongsTo(Election::class, 'election_id');
    }
}
