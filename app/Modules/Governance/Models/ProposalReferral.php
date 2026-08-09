<?php

namespace App\Modules\Governance\Models;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ProposalReferral extends Model
{
    protected $table = 'governance_proposal_referrals';

    protected $fillable = [
        'proposal_id', 'agenda_item_id', 'source_group_id', 'target_group_id',
        'referred_by', 'accepted_by', 'completed_by', 'status', 'request_notes',
        'response_notes', 'assessment', 'accepted_at', 'completed_at',
    ];

    protected $casts = [
        'assessment' => 'array',
        'accepted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function proposal() { return $this->belongsTo(Proposal::class); }
    public function agendaItem() { return $this->belongsTo(AgendaItem::class); }
    public function sourceGroup() { return $this->belongsTo(Group::class, 'source_group_id'); }
    public function targetGroup() { return $this->belongsTo(Group::class, 'target_group_id'); }
    public function referredBy() { return $this->belongsTo(User::class, 'referred_by'); }
    public function acceptedBy() { return $this->belongsTo(User::class, 'accepted_by'); }
    public function completedBy() { return $this->belongsTo(User::class, 'completed_by'); }
}
