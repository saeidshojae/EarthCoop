<?php

namespace App\Modules\Governance\Models;

use App\Models\Group;
use Illuminate\Database\Eloquent\Model;

class PublicContributionPlan extends Model
{
    protected $table = 'governance_public_contribution_plans';

    protected $fillable = [
        'economic_action_id', 'resolution_id', 'group_id', 'eligibility_snapshot_id',
        'status', 'total_required_gol', 'eligible_count', 'base_amount_gol',
        'remainder_gol', 'due_at', 'opened_at', 'closed_at', 'metadata',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function economicAction() { return $this->belongsTo(EconomicAction::class, 'economic_action_id'); }
    public function resolution() { return $this->belongsTo(Resolution::class); }
    public function group() { return $this->belongsTo(Group::class); }
    public function eligibilitySnapshot() { return $this->belongsTo(EligibilitySnapshot::class, 'eligibility_snapshot_id'); }
    public function obligations() { return $this->hasMany(PublicContributionObligation::class, 'plan_id'); }
}
