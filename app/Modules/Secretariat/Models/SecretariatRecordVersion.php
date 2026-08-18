<?php

namespace App\Modules\Secretariat\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SecretariatRecordVersion extends Model
{
    protected $guarded = [];

    protected $casts = [
        'approved_at' => 'datetime',
        'is_official' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if ($version->getOriginal('is_official')) {
                throw new LogicException('Official Secretariat versions are immutable.');
            }
        });

        static::deleting(function (self $version): void {
            if ($version->is_official) {
                throw new LogicException('Official Secretariat versions cannot be deleted.');
            }
        });
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(SecretariatRecord::class, 'record_id');
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
