<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class ElectionVoteSnapshotEntry extends Model
{
    protected $fillable = [
        'snapshot_run_id', 'election_id', 'voter_id', 'candidate_user_id', 'position',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Election vote snapshot entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Election vote snapshot entries are immutable.'));
    }

    public function run()
    {
        return $this->belongsTo(ElectionVoteSnapshotRun::class, 'snapshot_run_id');
    }
}
