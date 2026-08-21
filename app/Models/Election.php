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
