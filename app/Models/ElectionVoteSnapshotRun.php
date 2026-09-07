<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class ElectionVoteSnapshotRun extends Model
{
    protected $fillable = [
        'election_id', 'snapshot_version', 'cycle_identifier', 'stopped_at', 'snapshot_hash', 'vote_count',
    ];

    protected $casts = [
        'snapshot_version' => 'integer',
        'vote_count' => 'integer',
        'stopped_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Election vote snapshot runs are immutable.'));
        static::deleting(fn () => throw new LogicException('Election vote snapshot runs are immutable.'));
    }

    public function entries()
    {
        return $this->hasMany(ElectionVoteSnapshotEntry::class, 'snapshot_run_id');
    }
}
