<?php

namespace App\Modules\Secretariat\Models;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SecretariatDispatch extends Model
{
    protected $guarded = [];

    private bool $allowControlledMutation = false;

    protected $casts = [
        'metadata' => 'array',
        'expects_response' => 'boolean',
        'due_at' => 'datetime',
        'follow_up_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'received_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $dispatch): void {
            if (! $dispatch->allowControlledMutation) {
                throw new LogicException('Secretariat dispatch lifecycle and schedule must be mutated through SecretariatDispatchService.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Secretariat dispatch history is append-preserving and cannot be hard-deleted.');
        });
    }

    public function performControlledMutation(Closure $callback): mixed
    {
        $previous = $this->allowControlledMutation;
        $this->allowControlledMutation = true;

        try {
            return $callback($this);
        } finally {
            $this->allowControlledMutation = $previous;
        }
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(SecretariatRecord::class, 'record_id');
    }

    public function targetParty(): BelongsTo
    {
        return $this->belongsTo(SecretariatParty::class, 'target_party_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
