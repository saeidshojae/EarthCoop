<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupSession extends Model
{
    protected $fillable = [
        'group_id', 'created_by', 'ended_by', 'title', 'subject', 'agenda',
        'status', 'starts_at', 'started_at', 'ended_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime', 'started_at' => 'datetime', 'ended_at' => 'datetime',
    ];

    public function group() { return $this->belongsTo(Group::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
