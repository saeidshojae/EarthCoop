<?php

namespace App\Models;

use App\Enums\Elections\ElectionBallotCommentVisibility;
use App\Enums\Elections\ElectionVoteVisibility;
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
        'vote_visibility',
        'comment',
        'comment_visibility',
        'comment_anonymous',
        'request_uuid',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'vote_visibility' => ElectionVoteVisibility::class,
        'comment_visibility' => ElectionBallotCommentVisibility::class,
        'comment_anonymous' => 'boolean',
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
