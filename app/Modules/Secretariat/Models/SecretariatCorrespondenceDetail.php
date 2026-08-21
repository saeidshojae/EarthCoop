<?php

namespace App\Modules\Secretariat\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SecretariatCorrespondenceDetail extends Model
{
    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'received_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        $assertMutable = static function (self $detail): void {
            $record = $detail->record()->first();
            if ($record !== null && ! in_array($record->status, ['draft', 'cancelled'], true)) {
                throw new LogicException('Submitted or formal correspondence details are immutable; return the record to draft or create an amendment/new record instead.');
            }
        };

        static::updating($assertMutable);
        static::deleting($assertMutable);
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(SecretariatRecord::class, 'record_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
