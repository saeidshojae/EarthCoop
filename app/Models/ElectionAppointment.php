<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElectionAppointment extends Model
{
    protected $fillable = [
        'election_id', 'responsibility_offer_id', 'user_id', 'group_id',
        'position', 'group_role', 'appointment_kind', 'source_appointment_id',
        'status', 'appointed_at', 'ended_at', 'superseded_by_appointment_id',
        'actor', 'reason', 'metadata',
    ];

    protected $casts = [
        'appointed_at' => 'datetime',
        'ended_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function election() { return $this->belongsTo(Election::class); }
    public function offer() { return $this->belongsTo(ElectionResponsibilityOffer::class, 'responsibility_offer_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function group() { return $this->belongsTo(Group::class); }
    public function representation() { return $this->hasOne(ElectionRepresentationAssignment::class, 'appointment_id'); }
    public function sourceAppointment() { return $this->belongsTo(self::class, 'source_appointment_id'); }
    public function inheritedAppointments() { return $this->hasMany(self::class, 'source_appointment_id'); }
}
