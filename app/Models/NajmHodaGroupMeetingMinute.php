<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NajmHodaGroupMeetingMinute extends Model
{
    protected $fillable = [
        'group_session_id', 'group_id', 'status', 'summary', 'minutes',
        'evidence_snapshot', 'decision_candidates', 'action_candidates',
        'generated_by', 'approved_by', 'generated_at', 'approved_at',
    ];

    protected $casts = [
        'evidence_snapshot' => 'array',
        'decision_candidates' => 'array',
        'action_candidates' => 'array',
        'generated_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function session() { return $this->belongsTo(GroupSession::class, 'group_session_id'); }
    public function group() { return $this->belongsTo(Group::class); }
    public function generator() { return $this->belongsTo(User::class, 'generated_by'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
}
