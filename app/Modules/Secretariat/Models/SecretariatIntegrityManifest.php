<?php

namespace App\Modules\Secretariat\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class SecretariatIntegrityManifest extends Model
{
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'generated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Secretariat integrity manifests are append-only.'));
        static::deleting(fn () => throw new LogicException('Secretariat integrity manifests cannot be hard-deleted.'));
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(SecretariatRecordVersion::class, 'record_version_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function attestations(): HasMany
    {
        return $this->hasMany(SecretariatSignatureAttestation::class, 'manifest_id')->orderBy('id');
    }
}
