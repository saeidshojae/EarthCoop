<?php

namespace App\Modules\Secretariat\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SecretariatSignatureAttestation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'verified_at' => 'datetime',
        'evidence_metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Secretariat signature attestations are append-only; record a new verification event instead.'));
        static::deleting(fn () => throw new LogicException('Secretariat signature attestations cannot be hard-deleted.'));
    }

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(SecretariatIntegrityManifest::class, 'manifest_id');
    }

    public function contractSignatory(): BelongsTo
    {
        return $this->belongsTo(SecretariatContractSignatory::class, 'contract_signatory_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
