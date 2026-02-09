<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupUserSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'user_id',
        'muted',
        'archived',
        'notification_settings',
        'muted_until'
    ];

    protected $casts = [
        'muted' => 'boolean',
        'archived' => 'boolean',
        'notification_settings' => 'array',
        'muted_until' => 'datetime'
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if group is muted for user
     */
    public function isMuted(): bool
    {
        if (!$this->muted) {
            return false;
        }

        // Check if mute has expired
        if ($this->muted_until && $this->muted_until->isPast()) {
            $this->update(['muted' => false, 'muted_until' => null]);
            return false;
        }

        return true;
    }
}
