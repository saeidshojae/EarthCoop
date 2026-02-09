<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Group;

class NajmBaharAuditLog extends Model
{
    protected $table = 'najm_bahar_audit_logs';

    protected $fillable = [
        'group_id',
        'actor_user_id',
        'actor_role',
        'action',
        'account_number',
        'sub_account_code',
        'amount',
        'direction',
        'description',
        'meta',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'meta' => 'array',
        'amount' => 'integer',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id');
    }
}
