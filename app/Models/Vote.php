<?php

namespace App\Models;

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
    ];

    /**
     * Legacy relation only.
     *
     * Historical code used candidate_id both as Candidate.id and User.id. New
     * election-domain code must prefer candidateUser() / candidate_user_id.
     */
    public function candidate()
    {
        return $this->belongsTo(Candidate::class, 'candidate_id');
    }

    public function candidateUser()
    {
        return $this->belongsTo(User::class, 'candidate_user_id');
    }

    /**
     * Legacy selected-user relation. It intentionally remains bound to
     * candidate_id during E2 so existing callers are not silently reinterpreted.
     * New code must use candidateUser().
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'candidate_id');
    }

    public function election()
    {
        return $this->belongsTo(Election::class, 'election_id');
    }
}
