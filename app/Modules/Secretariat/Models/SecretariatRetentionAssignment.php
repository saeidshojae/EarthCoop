<?php

namespace App\Modules\Secretariat\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SecretariatRetentionAssignment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'retention_until' => 'datetime',
        'assigned_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Secretariat retention assignments are append-only; create a new assignment instead.'));
        static::deleting(fn () => throw new LogicException('Secretariat retention assignments cannot be hard-deleted.'));
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(SecretariatRecord::class, 'record_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
