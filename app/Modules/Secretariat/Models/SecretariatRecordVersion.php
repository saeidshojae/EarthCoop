<?php

namespace App\Modules\Secretariat\Models;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SecretariatRecordVersion extends Model
{
    protected $guarded = [];

    private bool $allowOfficialPromotion = false;

    protected $casts = [
        'approved_at' => 'datetime',
        'is_official' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if (! $version->allowOfficialPromotion) {
                throw new LogicException('Secretariat versions are append-only; create a new version instead of updating one.');
            }

            $allowed = ['is_official', 'approved_by', 'approved_at', 'updated_at'];
            foreach (array_keys($version->getDirty()) as $field) {
                if (! in_array($field, $allowed, true)) {
                    throw new LogicException("Official promotion cannot mutate Secretariat version field [{$field}].");
                }
            }

            if (! $version->is_official || $version->approved_by === null || $version->approved_at === null) {
                throw new LogicException('Official promotion requires official state, approver and approval time.');
            }
        });

        static::deleting(function (self $version): void {
            $recordStatus = $version->record()->value('status');
            $recordIsFormal = in_array((string) $recordStatus, ['registered', 'active', 'closed', 'archived', 'superseded', 'voided'], true);

            if ($version->is_official || $recordIsFormal) {
                throw new LogicException('Versions belonging to formal Secretariat records cannot be deleted.');
            }
        });
    }

    /**
     * Internal escape hatch used only by SecretariatVersionService while it
     * performs an audited/transactional approval. Ordinary code must never
     * update persisted versions in place.
     */
    public function performOfficialPromotion(Closure $callback): mixed
    {
        if ($this->is_official) {
            return $callback($this);
        }

        $previous = $this->allowOfficialPromotion;
        $this->allowOfficialPromotion = true;

        try {
            return $callback($this);
        } finally {
            $this->allowOfficialPromotion = $previous;
        }
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(SecretariatRecord::class, 'record_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
