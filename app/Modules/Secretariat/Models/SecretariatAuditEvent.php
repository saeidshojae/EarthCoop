<?php

namespace App\Modules\Secretariat\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SecretariatAuditEvent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'event_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Secretariat audit events are append-only.');
        });

        static::deleting(function (): void {
            throw new LogicException('Secretariat audit events are append-only.');
        });
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(SecretariatOffice::class, 'office_id');
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(SecretariatRecord::class, 'record_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
