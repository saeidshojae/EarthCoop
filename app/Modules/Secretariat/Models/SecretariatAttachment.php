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
        static::updating(function (): void {
            // Attachment rows describe immutable evidence: changing display name,
            // MIME, state or metadata in place would rewrite the historical
            // package. Future lifecycle changes must use an explicit event/model.
            throw new LogicException('Secretariat attachment metadata is append-only.');
        });

        static::deleting(function (self $attachment): void {
            $record = $attachment->record()->first();
            if ($record !== null && ! in_array($record->status, ['draft', 'cancelled'], true)) {
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
