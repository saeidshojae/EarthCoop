<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NativeDevice extends Model
{
    protected $fillable = [
        'public_id',
        'user_id',
        'platform',
        'app_version',
        'locale',
        'timezone',
        'push_capable',
        'push_provider',
        'push_token',
        'push_token_hash',
        'push_token_updated_at',
        'push_enabled_at',
        'push_disabled_at',
        'last_push_success_at',
        'last_push_failure_at',
        'last_push_failure_code',
        'last_seen_at',
        'revoked_at',
    ];

    protected $hidden = [
        'push_token',
        'push_token_hash',
    ];

    protected $casts = [
        'push_capable' => 'boolean',
        'push_token_updated_at' => 'datetime',
        'push_enabled_at' => 'datetime',
        'push_disabled_at' => 'datetime',
        'last_push_success_at' => 'datetime',
        'last_push_failure_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
