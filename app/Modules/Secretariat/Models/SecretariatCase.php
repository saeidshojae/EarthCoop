<?php

namespace App\Modules\Secretariat\Models;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

class SecretariatCase extends Model
{
    protected $guarded = [];

    private bool $allowControlledMutation = false;

    protected $casts = [
        'metadata' => 'array',
        'closed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $case): void {
            if (! $case->allowControlledMutation) {
                foreach (['office_id', 'case_number', 'title', 'summary', 'status', 'confidentiality', 'closed_by', 'closed_at', 'metadata'] as $field) {
                    if ($case->isDirty($field)) {
                        throw new LogicException("Secretariat case field [{$field}] must be mutated through SecretariatCaseService.");
                    }
                }
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Secretariat cases are historical containers and cannot be hard-deleted.');
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

    public function office(): BelongsTo
    {
        return $this->belongsTo(SecretariatOffice::class, 'office_id');
    }

    public function records(): BelongsToMany
    {
        return $this->belongsToMany(SecretariatRecord::class, 'secretariat_case_records', 'case_id', 'record_id')
            ->withPivot(['link_type', 'source_office_id', 'role', 'added_by', 'added_at', 'metadata'])
            ->withTimestamps();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
