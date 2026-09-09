<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLocationRelationship extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'location_id',
        'relationship_type',
        'started_at',
        'ended_at',
        'evidence',
        'explicit_transfer',
        'transfer_override',
        'changed_by_user_id',
        'change_reason',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'evidence' => 'array',
        'explicit_transfer' => 'boolean',
        'transfer_override' => 'boolean',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
