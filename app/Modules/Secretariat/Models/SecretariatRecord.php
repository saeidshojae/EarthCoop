<?php

namespace App\Modules\Secretariat\Models;

use App\Models\User;
use App\Modules\Secretariat\Services\SecretariatMorphMap;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class SecretariatRecord extends Model
{
    protected $guarded = [];

    private bool $allowControlledMutation = false;

    private const CONTROLLED_FIELDS = [
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

    private const FORMAL_STATUSES = [
        'registered',
        'active',
        'closed',
        'archived',
        'superseded',
        'voided',
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
            if (! $record->allowControlledMutation) {
                foreach (self::CONTROLLED_FIELDS as $field) {
                    if ($record->isDirty($field)) {
                        throw new LogicException("Secretariat record field [{$field}] must be mutated through a domain service.");
                    }
                }
            }

            $record->assertCurrentVersionBelongsToRecord();
            $record->assertFormalStateIntegrity();
        });

        static::deleting(function (self $record): void {
            if ($record->status !== 'draft' && $record->status !== 'cancelled') {
                throw new LogicException('Registered or formal Secretariat records cannot be hard-deleted.');
            }
        });
    }

    public function performControlledMutation(Closure $callback): mixed
    {
        $previous = $this->allowControlledMutation;
        $this->allowControlledMutation = true;

        try {
            return $callback($this);
        } finally {
            $this->allowControlledMutation = $previous;
        }
    }

    private function assertCurrentVersionBelongsToRecord(): void
    {
        if (! $this->isDirty('current_version_id') || $this->current_version_id === null) {
            return;
        }

        $belongs = SecretariatRecordVersion::query()
            ->whereKey($this->current_version_id)
            ->where('record_id', $this->id)
            ->exists();

        if (! $belongs) {
            throw new LogicException('Current Secretariat version must belong to the same record.');
        }
    }

    private function assertFormalStateIntegrity(): void
    {
        if (! in_array((string) $this->status, self::FORMAL_STATUSES, true)) {
            return;
        }

        foreach (['registry_number', 'registry_sequence', 'registry_year', 'registry_family', 'registered_by', 'registered_at', 'approved_by', 'approved_at', 'current_version_id'] as $field) {
            if ($this->{$field} === null || $this->{$field} === '') {
                throw new LogicException("Formal Secretariat record requires [{$field}].");
            }
        }

        $officialCurrentVersion = SecretariatRecordVersion::query()
            ->whereKey($this->current_version_id)
            ->where('record_id', $this->id)
            ->where('is_official', true)
            ->exists();

        if (! $officialCurrentVersion) {
            throw new LogicException('Formal Secretariat record requires an official current version belonging to the record.');
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

    public function attachments(): HasMany
    {
        return $this->hasMany(SecretariatAttachment::class, 'record_id')->orderBy('id');
    }

    public function parties(): HasMany
    {
        return $this->hasMany(SecretariatParty::class, 'record_id')->orderBy('id');
    }

    public function correspondenceDetail(): HasOne
    {
        return $this->hasOne(SecretariatCorrespondenceDetail::class, 'record_id');
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(SecretariatDispatch::class, 'record_id')->orderBy('id');
    }

    public function outgoingRelations(): HasMany
    {
        return $this->hasMany(SecretariatRelation::class, 'source_record_id')->orderBy('id');
    }

    public function incomingRelations(): HasMany
    {
        return $this->hasMany(SecretariatRelation::class, 'target_record_id')->orderBy('id');
    }

    public function aclEntries(): HasMany
    {
        return $this->hasMany(SecretariatAclEntry::class, 'record_id')->orderBy('id');
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
