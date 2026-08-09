<?php

namespace App\Modules\Governance\Models;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AgendaItem extends Model
{
    protected $table = 'governance_agenda_items';

    protected $fillable = [
        'proposal_id', 'group_id', 'added_by', 'status',
        'professional_referral_required', 'referral_notes', 'scheduled_at', 'metadata',
    ];

    protected $casts = [
        'professional_referral_required' => 'boolean',
        'scheduled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function proposal() { return $this->belongsTo(Proposal::class); }
    public function group() { return $this->belongsTo(Group::class); }
    public function addedBy() { return $this->belongsTo(User::class, 'added_by'); }
    public function referrals() { return $this->hasMany(ProposalReferral::class, 'agenda_item_id'); }
}
