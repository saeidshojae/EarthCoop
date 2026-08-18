<?php

namespace App\Modules\Secretariat\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SecretariatAttachment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $attachment): void {
            foreach (['record_id', 'version_id', 'storage_disk', 'storage_key', 'checksum', 'file_size', 'uploaded_by', 'uploaded_at'] as $field) {
                if ($attachment->isDirty($field)) {
                    throw new LogicException("Secretariat attachment identity field [{$field}] is immutable.");
                }
            }
        });

        static::deleting(function (self $attachment): void {
            $record = $attachment->record()->first();
            if ($record !== null && $record->status !== 'draft' && $record->status !== 'cancelled') {
                throw new LogicException('Attachments of formal Secretariat records cannot be hard-deleted.');
            }
        });
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(SecretariatRecord::class, 'record_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(SecretariatRecordVersion::class, 'version_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
