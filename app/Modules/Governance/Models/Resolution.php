<?php

namespace App\Modules\Governance\Models;

use App\Models\Group;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Resolution extends Model
{
    protected $table = 'governance_resolutions';

    protected $fillable = [
        'proposal_id', 'group_id', 'poll_id', 'adopted_by', 'type', 'status',
        'effect_status', 'quorum_required_percent', 'approval_required_percent',
        'eligible_voter_count', 'votes_cast', 'votes_for', 'votes_against',
        'votes_abstain', 'financial_effect', 'metadata', 'effective_at', 'adopted_at',
    ];

    protected $casts = [
        'financial_effect' => 'array',
        'metadata' => 'array',
        'effective_at' => 'datetime',
        'adopted_at' => 'datetime',
        'quorum_required_percent' => 'decimal:2',
        'approval_required_percent' => 'decimal:2',
    ];

    public function proposal() { return $this->belongsTo(Proposal::class); }
    public function group() { return $this->belongsTo(Group::class); }
    public function poll() { return $this->belongsTo(Poll::class); }
    public function adopter() { return $this->belongsTo(User::class, 'adopted_by'); }
}
