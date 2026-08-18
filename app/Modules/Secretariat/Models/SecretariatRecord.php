<?php

namespace App\Modules\Secretariat\Models;

use App\Models\User;
use App\Modules\Secretariat\Services\SecretariatMorphMap;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class SecretariatRecord extends Model
{
    protected $guarded = [];

    private bool $allowFormalMutation = false;

    private const FORMAL_IMMUTABLE_FIELDS = [
        'office_id',
        'registry_number',
        'registry_sequence',
        'registry_year',
        'registry_family',
        'record_type',
        'direction',
        'title',
        'subject',
        'summary',
        'status',
        'confidentiality',
        'current_version_id',
        'source_type',
        'source_id',
        'registered_by',
        'registered_at',
        'approved_by',
        'approved_at',
        'effective_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'registered_at' => 'datetime',
        'approved_at' => 'datetime',
        'effective_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        SecretariatMorphMap::register();

        static::updating(function (self $record): void {
            $originalStatus = (string) $record->getOriginal('status');
            $isFormal = in_array($originalStatus, ['registered', 'active', 'closed', 'archived', 'superseded', 'voided'], true);

            if (! $isFormal || $record->allowFormalMutation) {
                return;
            }

            foreach (self::FORMAL_IMMUTABLE_FIELDS as $field) {
                if ($record->isDirty($field)) {
                    throw new LogicException("Formal Secretariat field [{$field}] cannot be overwritten directly.");
                }
            }
        });

        static::deleting(function (self $record): void {
            if ($record->status !== 'draft' && $record->status !== 'cancelled') {
                throw new LogicException('Registered or formal Secretariat records cannot be hard-deleted.');
            }
        });
    }

    /**
     * Internal escape hatch for deterministic Secretariat domain services only.
     *
     * Formal records are otherwise immutable through ordinary Eloquent updates.
     * Callers using this method are responsible for transaction, lifecycle and
     * audit invariants; controllers/integrations must not use it directly.
     */
    public function performFormalMutation(Closure $callback): mixed
    {
        $previous = $this->allowFormalMutation;
        $this->allowFormalMutation = true;

        try {
            return $callback($this);
        } finally {
            $this->allowFormalMutation = $previous;
        }
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
