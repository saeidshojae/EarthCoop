<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupSessionParticipationRequest extends Model
{
    protected $fillable = [
        'group_id', 'user_id', 'status', 'message', 'resolved_by', 'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
