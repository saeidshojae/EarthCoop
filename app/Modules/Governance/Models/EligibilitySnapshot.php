<?php

namespace App\Modules\Governance\Models;

use App\Models\Group;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class EligibilitySnapshot extends Model
{
    protected $table = 'governance_eligibility_snapshots';

    protected $fillable = [
        'group_id', 'poll_id', 'captured_by', 'purpose', 'status', 'eligible_count',
        'chunk_size', 'chunk_count', 'criteria', 'membership_fingerprint', 'captured_at',
    ];

    protected $casts = [
        'criteria' => 'array',
        'captured_at' => 'datetime',
    ];

    public function group() { return $this->belongsTo(Group::class); }
    public function poll() { return $this->belongsTo(Poll::class); }
    public function capturedBy() { return $this->belongsTo(User::class, 'captured_by'); }
    public function chunks() { return $this->hasMany(EligibilitySnapshotChunk::class, 'snapshot_id')->orderBy('chunk_index'); }
}
