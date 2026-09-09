<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationTypeRelation extends Model
{
    protected $fillable = [
        'location_schema_id', 'parent_type_id', 'child_type_id', 'metadata',
    ];

    protected $casts = ['metadata' => 'array'];

    public function schema(): BelongsTo
    {
        return $this->belongsTo(LocationSchema::class, 'location_schema_id');
    }

    public function parentType(): BelongsTo
    {
        return $this->belongsTo(LocationType::class, 'parent_type_id');
    }

    public function childType(): BelongsTo
    {
        return $this->belongsTo(LocationType::class, 'child_type_id');
    }
}
