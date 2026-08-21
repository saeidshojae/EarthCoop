<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class ElectionTallyResult extends Model
{
    protected $fillable = [
        'election_id',
        'candidate_user_id',
        'position',
        'vote_count',
        'rank',
        'within_seat_cutoff',
        'cycle_identifier',
        'stopped_at',
        'vote_snapshot_hash',
        'draw_seed_version',
        'draw_seed',
        'tie_break_version',
        'tie_break_key',
        'tallied_at',
    ];

    protected $casts = [
        'vote_count' => 'integer',
        'rank' => 'integer',
        'within_seat_cutoff' => 'boolean',
        'stopped_at' => 'datetime',
        'tallied_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Election tally snapshots are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Election tally snapshots are immutable.');
        });
    }

    public function election()
    {
        return $this->belongsTo(Election::class);
    }

    public function candidateUser()
    {
        return $this->belongsTo(User::class, 'candidate_user_id');
    }
}
