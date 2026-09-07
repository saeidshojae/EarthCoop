<?php

namespace App\Modules\Secretariat\Models;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

            $recordType = (string) $version->record()->value('record_type');
            if (in_array($recordType, ['contract', 'memorandum_of_understanding', 'agreement'], true)) {
                $hasDetails = SecretariatContractVersionDetail::query()
                    ->where('record_version_id', $version->id)
                    ->exists();
                $hasSignatory = SecretariatContractSignatory::query()
                    ->where('record_version_id', $version->id)
                    ->exists();

                if (! $hasDetails || ! $hasSignatory) {
                    throw new LogicException('Formal contract/MOU/agreement versions require contract details and at least one signatory snapshot.');
                }
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

    public function attachments(): HasMany
    {
        return $this->hasMany(SecretariatAttachment::class, 'version_id')->orderBy('id');
    }

    public function contractDetails(): HasOne
    {
        return $this->hasOne(SecretariatContractVersionDetail::class, 'record_version_id');
    }

    public function contractSignatories(): HasMany
    {
        return $this->hasMany(SecretariatContractSignatory::class, 'record_version_id')->orderBy('signing_order')->orderBy('id');
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
