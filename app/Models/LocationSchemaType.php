<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationSchemaType extends Model
{
    protected $fillable = [
        'location_schema_id', 'location_type_id', 'is_root',
        'is_residence_endpoint', 'sort_order', 'metadata',
    ];

    protected $casts = [
        'is_root' => 'boolean',
        'is_residence_endpoint' => 'boolean',
        'metadata' => 'array',
    ];

    public function schema(): BelongsTo
    {
        return $this->belongsTo(LocationSchema::class, 'location_schema_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LocationType::class, 'location_type_id');
    }
}
