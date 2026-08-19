<?php

namespace App\Modules\Secretariat\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SecretariatExportPackage extends Model
{
    protected $guarded = [];

    protected $casts = [
        'package_manifest' => 'array',
        'generated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Secretariat export packages are immutable. Generate a new package instead.'));
        static::deleting(fn () => throw new LogicException('Secretariat export packages cannot be hard-deleted.'));
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(SecretariatRecordVersion::class, 'record_version_id');
    }

    public function integrityManifest(): BelongsTo
    {
        return $this->belongsTo(SecretariatIntegrityManifest::class, 'integrity_manifest_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
