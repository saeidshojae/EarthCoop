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
        'starts_at',
        'ends_at',
        'is_closed',
        'lifecycle_status',
        'second_finish_time',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_closed' => 'boolean',
        'lifecycle_status' => ElectionLifecycleStatus::class,
    ];

    /**
     * Compatibility bridge: legacy code still closes an election through
     * is_closed. A proven close may safely advance the canonical projection to
     * closed; writing false cannot tell scheduled from open, so it is not
     * guessed here.
     */
    public function setIsClosedAttribute(mixed $value): void
    {
        $closed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $this->attributes['is_closed'] = $closed ?? (bool) $value;

        if ((bool) $this->attributes['is_closed']) {
            $this->attributes['lifecycle_status'] = ElectionLifecycleStatus::Closed->value;
        }
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function candidates()
    {
        return $this->hasMany(Candidate::class);
    }

    public function yourVotes()
    {
        return $this->hasMany(Vote::class, 'election_id')
            ->where('voter_id', auth()->id());
    }
}
