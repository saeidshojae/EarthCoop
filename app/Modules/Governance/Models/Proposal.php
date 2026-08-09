<?php

namespace App\Modules\Governance\Models;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Proposal extends Model
{
    protected $table = 'governance_proposals';

    protected $fillable = [
        'group_id', 'created_by', 'type', 'title', 'summary', 'description',
        'status', 'support_count', 'support_threshold', 'metadata',
        'submitted_at', 'supported_at', 'agenda_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'submitted_at' => 'datetime',
        'supported_at' => 'datetime',
        'agenda_at' => 'datetime',
    ];

    public function group() { return $this->belongsTo(Group::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function supports() { return $this->hasMany(ProposalSupport::class); }
    public function agendaItems() { return $this->hasMany(AgendaItem::class); }
    public function resolutions() { return $this->hasMany(Resolution::class); }
}
