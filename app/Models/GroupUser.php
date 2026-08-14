<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GroupUser extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'group_user';
    protected $fillable = [
        'group_id', 'user_id', 'role', 'status', 'expired', 'last_read_message_id',
        'last_read_feed_sequence', 'session_write_allowed', 'role_override_active',
        'role_override_original_role', 'role_override_started_at', 'role_override_expires_at',
        'role_override_changed_by', 'role_override_source',
    ];

    protected $casts = [
        'session_write_allowed' => 'boolean',
        'role_override_active' => 'boolean',
        'role_override_started_at' => 'datetime',
        'role_override_expires_at' => 'datetime',
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function group(){
        return $this->belongsTo(Group::class);
    }
}
