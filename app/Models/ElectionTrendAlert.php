<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElectionTrendAlert extends Model
{
    protected $fillable = [
        'election_id', 'candidate_user_id', 'position', 'window_start', 'window_end',
        'trend_direction', 'fingerprint', 'notified_at', 'metadata',
    ];

    protected $casts = [
        'window_start' => 'date',
        'window_end' => 'date',
        'notified_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function election() { return $this->belongsTo(Election::class); }
    public function candidate() { return $this->belongsTo(User::class, 'candidate_user_id'); }
}
