<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class ElectionBallotEvent extends Model
{
    protected $fillable = [
        'election_id',
        'voter_id',
        'event_type',
        'candidate_user_id',
        'previous_candidate_user_id',
        'position',
        'previous_position',
        'request_uuid',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Election ballot audit events are append-only.');
        });

        static::deleting(function (): never {
            throw new LogicException('Election ballot audit events are append-only.');
        });
    }

    public function election()
    {
        return $this->belongsTo(Election::class);
    }

    public function voter()
    {
        return $this->belongsTo(User::class, 'voter_id');
    }

    public function candidateUser()
    {
        return $this->belongsTo(User::class, 'candidate_user_id');
    }
}
