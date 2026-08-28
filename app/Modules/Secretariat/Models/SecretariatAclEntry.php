<?php

namespace App\Modules\Secretariat\Models;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SecretariatAclEntry extends Model
{
    protected $guarded = [];

    private bool $allowRevocation = false;

    protected $casts = [
        'granted_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $entry): void {
            if (! $entry->allowRevocation) {
                throw new LogicException('Secretariat ACL grants are append-only; only controlled revocation may mutate a grant.');
            }

            foreach (array_keys($entry->getDirty()) as $field) {
                if (! in_array($field, ['revoked_by', 'revoked_at'], true)) {
                    throw new LogicException("Secretariat ACL field [{$field}] cannot change during revocation.");
                }
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Secretariat ACL history cannot be hard-deleted; revoke the grant instead.');
        });
    }

    public function performRevocation(Closure $callback): mixed
    {
        $previous = $this->allowRevocation;
        $this->allowRevocation = true;

        try {
            return $callback($this);
        } finally {
            $this->allowRevocation = $previous;
        }
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(SecretariatRecord::class, 'record_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
