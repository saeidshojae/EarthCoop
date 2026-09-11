<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationRelation extends Model
{
    protected $fillable = [
        'from_location_id', 'to_location_id', 'relation_type', 'effective_at', 'metadata',
    ];

    protected $casts = [
        'effective_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }
}
