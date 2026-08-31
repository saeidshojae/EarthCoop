<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FounderAdminSettingIntent extends Model
{
    public const PENDING = 'pending';
    public const REJECTED = 'rejected';
    public const EXECUTED = 'executed';

    protected $guarded = [];

    protected $casts = [
        'setting_value' => 'array',
        'executed_at' => 'datetime',
    ];
}
