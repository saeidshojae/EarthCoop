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
        'last_seen_at',
        'revoked_at',
    ];

    protected $casts = [
        'push_capable' => 'boolean',
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
