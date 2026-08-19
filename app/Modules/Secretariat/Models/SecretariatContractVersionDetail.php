<?php

namespace App\Modules\Secretariat\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SecretariatContractVersionDetail extends Model
{
    protected $guarded = [];

    protected $casts = [
        'effective_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        $assertDraftVersion = static function (self $detail): void {
            $version = $detail->version()->with('record')->first();
            if ($version !== null && ($version->is_official || in_array((string) $version->record?->status, ['registered', 'active', 'closed', 'archived', 'superseded', 'voided'], true))) {
                throw new LogicException('Formal contract version metadata is immutable; create an amendment version instead.');
            }
        };

        static::updating($assertDraftVersion);
        static::deleting($assertDraftVersion);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(SecretariatRecordVersion::class, 'record_version_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
