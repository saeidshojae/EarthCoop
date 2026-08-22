<?php

namespace App\Models;

use App\Services\Elections\ElectionConflictPolicyService;
use Illuminate\Database\Eloquent\Model;

class ElectionAppointment extends Model
{
    protected $fillable = [
        'election_id', 'responsibility_offer_id', 'user_id', 'group_id',
        'position', 'group_role', 'appointment_kind', 'source_appointment_id',
        'status', 'review_state', 'appointed_at', 'ended_at', 'superseded_by_appointment_id',
        'actor', 'reason', 'metadata',
    ];

    protected $casts = [
        'appointed_at' => 'datetime',
        'ended_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (ElectionAppointment $appointment): void {
            if (($appointment->appointment_kind ?? 'direct') !== 'direct') return;
            $user = User::query()->findOrFail($appointment->user_id);
            $group = Group::query()->findOrFail($appointment->group_id);
            app(ElectionConflictPolicyService::class)->enforceBeforeDirectAppointment($user, $group, (string) $appointment->position);
        });

        static::updated(function (ElectionAppointment $appointment): void {
            if (! $appointment->wasChanged('status')
                || $appointment->status !== 'revoked'
                || $appointment->appointment_kind !== 'direct') {
                return;
            }

            ElectionVacancy::query()->firstOrCreate(
                ['source_appointment_id' => $appointment->id],
                [
                    'election_id' => $appointment->election_id,
                    'user_id' => $appointment->user_id,
                    'group_id' => $appointment->group_id,
                    'position' => $appointment->position,
                    'continuity_mode' => 'immediate',
                    'status' => 'open',
                    'opened_at' => $appointment->ended_at ?? now(),
                    'actor' => $appointment->actor ?: 'election_appointment_service',
                    'reason' => $appointment->reason ?: 'appointment_revoked',
                    'metadata' => [
                        'source_appointment_kind' => $appointment->appointment_kind,
                        'source_responsibility_offer_id' => (int) $appointment->responsibility_offer_id,
                    ],
                ],
            );
        });
    }

    public function election() { return $this->belongsTo(Election::class); }
    public function offer() { return $this->belongsTo(ElectionResponsibilityOffer::class, 'responsibility_offer_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function group() { return $this->belongsTo(Group::class); }
    public function representation() { return $this->hasOne(ElectionRepresentationAssignment::class, 'appointment_id'); }
    public function sourceAppointment() { return $this->belongsTo(self::class, 'source_appointment_id'); }
    public function inheritedAppointments() { return $this->hasMany(self::class, 'source_appointment_id'); }
    public function vacancy() { return $this->hasOne(ElectionVacancy::class, 'source_appointment_id'); }
    public function processReviews() { return $this->hasMany(ElectionProcessReview::class, 'appointment_id'); }
}
