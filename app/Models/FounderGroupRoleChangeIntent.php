<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FounderGroupRoleChangeIntent extends Model
{
    public const PENDING = 'pending';
    public const REJECTED = 'rejected';
    public const EXECUTED = 'executed';

    protected $guarded = [];

    protected $casts = [
        'target_role' => 'integer',
        'expires_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    public function membership()
    {
        return $this->belongsTo(GroupUser::class, 'group_user_id');
    }
}
