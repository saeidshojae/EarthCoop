<?php

namespace App\Modules\Secretariat\Models;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SecretariatParty extends Model
{
    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        $assertMutable = static function (self $party): void {
            $record = $party->record()->first();
            if ($record !== null && ! in_array($record->status, ['draft', 'cancelled'], true)) {
                throw new LogicException('Submitted or formal Secretariat party snapshots are immutable; return the record to draft or create an amendment/new record instead.');
            }
        };

        static::updating($assertMutable);
        static::deleting($assertMutable);
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(SecretariatRecord::class, 'record_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
