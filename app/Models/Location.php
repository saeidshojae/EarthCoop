<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'parent_id', 'level',
        'location_schema_id', 'location_type_id', 'country_code',
        'canonical_name', 'localized_names', 'status',
        'centroid_latitude', 'centroid_longitude', 'valid_from', 'valid_to',
        'provenance', 'metadata',
    ];

    protected $casts = [
        'localized_names' => 'array',
        'provenance' => 'array',
        'metadata' => 'array',
        'centroid_latitude' => 'decimal:7',
        'centroid_longitude' => 'decimal:7',
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Location $location): void {
            if (blank($location->canonical_name) && filled($location->name)) {
                $location->canonical_name = $location->name;
            }

            if (blank($location->name) && filled($location->canonical_name)) {
                $location->name = $location->canonical_name;
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Location::class, 'parent_id');
    }

    public function schema(): BelongsTo
    {
        return $this->belongsTo(LocationSchema::class, 'location_schema_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LocationType::class, 'location_type_id');
    }

    public function externalIds(): HasMany
    {
        return $this->hasMany(LocationExternalId::class);
    }

    public function outgoingRelations(): HasMany
    {
        return $this->hasMany(LocationRelation::class, 'from_location_id');
    }

    public function incomingRelations(): HasMany
    {
        return $this->hasMany(LocationRelation::class, 'to_location_id');
    }
}
