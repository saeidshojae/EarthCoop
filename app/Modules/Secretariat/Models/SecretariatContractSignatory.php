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
        $assertMutableVersion = static function (self $signatory): void {
            $version = $signatory->version()->first();
            if ($version !== null && $version->is_official) {
                throw new LogicException('Official contract signatory snapshots are immutable; create an amendment version instead.');
            }
        };

        static::updating($assertMutableVersion);
        static::deleting($assertMutableVersion);
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
