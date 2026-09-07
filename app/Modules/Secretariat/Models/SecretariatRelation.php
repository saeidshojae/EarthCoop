<?php

namespace App\Modules\Secretariat\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SecretariatRelation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Secretariat relations are append-only.');
        });

        static::deleting(function (self $relation): void {
            $source = $relation->sourceRecord()->first();
            $target = $relation->targetRecord()->first();
            $formal = static fn (?SecretariatRecord $record): bool => $record !== null
                && ! in_array($record->status, ['draft', 'cancelled'], true);

            if ($formal($source) || $formal($target)) {
                throw new LogicException('Relations involving formal Secretariat records cannot be hard-deleted.');
            }
        });
    }

    public function sourceRecord(): BelongsTo
    {
        return $this->belongsTo(SecretariatRecord::class, 'source_record_id');
    }

    public function targetRecord(): BelongsTo
    {
        return $this->belongsTo(SecretariatRecord::class, 'target_record_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
