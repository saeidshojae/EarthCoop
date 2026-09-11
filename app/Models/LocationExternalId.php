<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationExternalId extends Model
{
    protected $fillable = [
        'location_id', 'source', 'dataset_version', 'external_id', 'metadata',
    ];

    protected $casts = ['metadata' => 'array'];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
