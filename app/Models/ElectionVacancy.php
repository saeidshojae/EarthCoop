<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElectionVacancy extends Model
{
    protected $fillable = [
        'election_id', 'source_appointment_id', 'user_id', 'group_id', 'position',
        'continuity_mode', 'status', 'opened_at', 'resolved_at', 'replacement_offer_id',
        'replacement_appointment_id', 'actor', 'reason', 'metadata',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'resolved_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function election() { return $this->belongsTo(Election::class); }
    public function sourceAppointment() { return $this->belongsTo(ElectionAppointment::class, 'source_appointment_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function group() { return $this->belongsTo(Group::class); }
    public function replacementOffer() { return $this->belongsTo(ElectionResponsibilityOffer::class, 'replacement_offer_id'); }
    public function replacementAppointment() { return $this->belongsTo(ElectionAppointment::class, 'replacement_appointment_id'); }
}
