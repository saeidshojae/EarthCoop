<?php

namespace App\Modules\Governance\Models;

use Illuminate\Database\Eloquent\Model;

class EligibilitySnapshotChunk extends Model
{
    protected $table = 'governance_eligibility_snapshot_chunks';

    protected $fillable = [
        'snapshot_id', 'chunk_index', 'member_count', 'first_user_id', 'last_user_id', 'member_ids',
    ];

    protected $casts = ['member_ids' => 'array'];

    public function snapshot() { return $this->belongsTo(EligibilitySnapshot::class, 'snapshot_id'); }
}
