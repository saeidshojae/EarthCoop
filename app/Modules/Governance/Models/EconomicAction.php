<?php

namespace App\Modules\Governance\Models;

use App\Models\Group;
use Illuminate\Database\Eloquent\Model;

class EconomicAction extends Model
{
    protected $table = 'governance_economic_actions';

    protected $fillable = [
        'resolution_id', 'group_id', 'eligibility_snapshot_id', 'action_type', 'status',
        'idempotency_key', 'payload', 'result', 'failure_reason', 'attempts',
        'claimed_at', 'completed_at', 'failed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
        'claimed_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function resolution() { return $this->belongsTo(Resolution::class); }
    public function group() { return $this->belongsTo(Group::class); }
    public function eligibilitySnapshot() { return $this->belongsTo(EligibilitySnapshot::class, 'eligibility_snapshot_id'); }
}
