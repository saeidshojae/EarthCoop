<?php

namespace App\Models;

use App\Enums\Elections\ElectionLifecycleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Election extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'cycle_number',
        'previous_election_id',
        'policy_version_id',
        'starts_at',
        'ends_at',
        'is_closed',
        'lifecycle_status',
        'eligibility_snapshot_captured_at',
        'eligibility_snapshot_version',
        'second_finish_time',
    ];

    protected $casts = [
        'cycle_number' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_closed' => 'boolean',
        'lifecycle_status' => ElectionLifecycleStatus::class,
        'eligibility_snapshot_captured_at' => 'datetime',
    ];

    public function setIsClosedAttribute(mixed $value): void
    {
        $closed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $this->attributes['is_closed'] = $closed ?? (bool) $value;

        if (! (bool) $this->attributes['is_closed']) {
            return;
        }

        $current = $this->attributes['lifecycle_status'] ?? null;
        if ($current instanceof ElectionLifecycleStatus) {
            $current = $current->value;
        }

        if ($current === null || $current === '' || in_array($current, [
            ElectionLifecycleStatus::Scheduled->value,
            ElectionLifecycleStatus::Open->value,
        ], true)) {
            $this->attributes['lifecycle_status'] = ElectionLifecycleStatus::Closed->value;
        }
    }

    public function group() { return $this->belongsTo(Group::class); }
    public function previousCycle() { return $this->belongsTo(self::class, 'previous_election_id'); }
    public function nextCycle() { return $this->hasOne(self::class, 'previous_election_id'); }
    public function policyVersion() { return $this->belongsTo(ElectionPolicyVersion::class, 'policy_version_id'); }
    public function candidates() { return $this->hasMany(Candidate::class); }
    public function eligibilitySnapshots() { return $this->hasMany(ElectionEligibilitySnapshot::class); }
    public function lifecycleTransitions() { return $this->hasMany(ElectionLifecycleTransition::class); }
    public function responsibilityOffers() { return $this->hasMany(ElectionResponsibilityOffer::class); }
    public function appointments() { return $this->hasMany(ElectionAppointment::class); }
    public function vacancies() { return $this->hasMany(ElectionVacancy::class); }

    public function yourVotes()
    {
        return $this->hasMany(Vote::class, 'election_id')
            ->where('voter_id', auth()->id());
    }
}
