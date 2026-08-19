<?php

namespace App\Modules\Secretariat\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SecretariatAttachmentScan extends Model
{
    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'scanned_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Secretariat attachment scan evidence is append-only.'));
        static::deleting(fn () => throw new LogicException('Secretariat attachment scan evidence cannot be deleted.'));
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(SecretariatAttachment::class, 'attachment_id');
    }
}
