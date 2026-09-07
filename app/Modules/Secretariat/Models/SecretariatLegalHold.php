<?php

namespace App\Modules\Secretariat\Models;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SecretariatLegalHold extends Model
{
    protected $guarded = [];

    private bool $allowRelease = false;

    protected $casts = [
        'placed_at' => 'datetime',
        'released_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $hold): void {
            if (! $hold->allowRelease) {
                throw new LogicException('Secretariat legal holds may only be released through the controlled hold service.');
            }
            $allowed = ['released_by', 'released_at', 'release_reason', 'updated_at'];
            foreach (array_keys($hold->getDirty()) as $field) {
                if (! in_array($field, $allowed, true)) {
                    throw new LogicException("Legal hold release cannot mutate field [{$field}].");
                }
            }
            if ($hold->released_by === null || $hold->released_at === null) {
                throw new LogicException('Legal hold release requires actor and release timestamp.');
            }
        });
        static::deleting(fn () => throw new LogicException('Secretariat legal holds cannot be hard-deleted.'));
    }

    public function performRelease(Closure $callback): mixed
    {
        $previous = $this->allowRelease;
        $this->allowRelease = true;
        try {
            return $callback($this);
        } finally {
            $this->allowRelease = $previous;
        }
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(SecretariatRecord::class, 'record_id');
    }

    public function placedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'placed_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
