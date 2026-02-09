<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
    use HasFactory;
    protected $fillable = ['voter_id', 'election_id', 'candidate_id', 'position'];

    public function candidate()
    {
        return $this->belongsTo(Candidate::class, 'candidate_id');
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
