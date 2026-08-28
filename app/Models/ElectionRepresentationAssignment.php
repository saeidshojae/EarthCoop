<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ElectionRepresentationAssignment extends Model
{
    protected $fillable = [
        'appointment_id', 'user_id', 'source_group_id', 'represented_group_id',
        'status', 'activated_at', 'ended_at', 'reason', 'metadata',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'ended_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function appointment() { return $this->belongsTo(ElectionAppointment::class, 'appointment_id'); }
    public function user() { return $this->belongsTo(User::class); }
    public function sourceGroup() { return $this->belongsTo(Group::class, 'source_group_id'); }
    public function representedGroup() { return $this->belongsTo(Group::class, 'represented_group_id'); }
}
