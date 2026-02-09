<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class AdminActionLog extends Model
{
    protected $table = 'admin_action_logs';

    protected $fillable = [
        'admin_user_id',
        'action',
        'target_type',
        'target_id',
        'description',
        'metadata',
        'ip_address',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function adminUser()
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }
}
