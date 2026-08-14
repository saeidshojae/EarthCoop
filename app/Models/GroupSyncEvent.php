<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupSyncEvent extends Model
{
    protected $fillable = [
        'group_id',
        'event_type',
        'action',
        'content_type',
        'content_id',
        'actor_id',
        'payload',
        'occurred_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];
}
