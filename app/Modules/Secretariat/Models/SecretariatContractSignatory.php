<?php

namespace App\Modules\Secretariat\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SecretariatContractSignatory extends Model
{
    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        $assertDraftVersion = static function (self $signatory): void {
            $version = $signatory->version()->with('record')->first();
            if ($version !== null && ($version->is_official || in_array((string) $version->record?->status, ['registered', 'active', 'closed', 'archived', 'superseded', 'voided'], true))) {
                throw new LogicException('Formal contract signatory snapshots are immutable; create an amendment version instead.');
            }
        };

        static::updating($assertDraftVersion);
        static::deleting($assertDraftVersion);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(SecretariatRecordVersion::class, 'record_version_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(SecretariatParty::class, 'party_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
