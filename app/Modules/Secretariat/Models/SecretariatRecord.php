<?php

namespace App\Modules\Secretariat\Models;

use App\Models\User;
use App\Modules\Secretariat\Services\SecretariatMorphMap;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class SecretariatRecord extends Model
{
    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'registered_at' => 'datetime',
        'approved_at' => 'datetime',
        'effective_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        SecretariatMorphMap::register();

        static::deleting(function (self $record): void {
            if ($record->status !== 'draft' && $record->status !== 'cancelled') {
                throw new LogicException('Registered or formal Secretariat records cannot be hard-deleted.');
            }
        });
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(SecretariatOffice::class, 'office_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SecretariatRecordVersion::class, 'record_id')->orderBy('version_number');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(SecretariatRecordVersion::class, 'current_version_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(SecretariatAuditEvent::class, 'record_id');
    }
}
