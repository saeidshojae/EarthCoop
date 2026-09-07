<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElectionProcessReview extends Model
{
    public const GROUNDS = [
        'membership_eligibility',
        'ballot_limit',
        'stop_time',
        'ranking',
        'tie_break',
        'representation',
        'offer_acceptance',
        'conflict_policy',
        'technical_error',
    ];

    protected $fillable = [
        'election_id', 'requester_user_id', 'subject_user_id', 'appointment_id',
        'ground', 'challenged_event', 'challenged_event_id', 'event_occurred_at',
        'statement', 'automatic_status', 'automatic_result', 'human_status',
        'support_count', 'human_deadline_at', 'human_requested_at', 'decision_due_at',
        'interim_state', 'interim_reason', 'decided_at', 'decided_by', 'decision',
        'decision_reason', 'remediation_reference',
    ];

    protected $casts = [
        'event_occurred_at' => 'datetime',
        'automatic_result' => 'array',
        'human_deadline_at' => 'datetime',
        'human_requested_at' => 'datetime',
        'decision_due_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function election() { return $this->belongsTo(Election::class); }
    public function requester() { return $this->belongsTo(User::class, 'requester_user_id'); }
    public function subject() { return $this->belongsTo(User::class, 'subject_user_id'); }
    public function appointment() { return $this->belongsTo(ElectionAppointment::class); }
    public function endorsements() { return $this->hasMany(ElectionProcessReviewEndorsement::class, 'review_id'); }
}
