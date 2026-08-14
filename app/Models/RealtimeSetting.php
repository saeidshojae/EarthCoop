<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RealtimeSetting extends Model
{
    protected $fillable = [
        'enabled', 'transport', 'provider', 'fallback_to_polling', 'use_env_credentials',
        'app_id', 'app_key', 'app_secret', 'host', 'port', 'scheme', 'cluster',
        'polling_interval_ms', 'last_test_status', 'last_test_message', 'last_tested_at', 'updated_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'fallback_to_polling' => 'boolean',
        'use_env_credentials' => 'boolean',
        'app_secret' => 'encrypted',
        'port' => 'integer',
        'polling_interval_ms' => 'integer',
        'last_tested_at' => 'datetime',
    ];
}
