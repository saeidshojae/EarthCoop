<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LocationSchema extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'country_code', 'name', 'version', 'status', 'metadata'];

    protected $casts = ['metadata' => 'array'];

    public function types(): BelongsToMany
    {
        return $this->belongsToMany(LocationType::class, 'location_schema_types')
            ->withPivot(['is_root', 'is_residence_endpoint', 'sort_order', 'metadata'])
            ->withTimestamps();
    }

    public function typeRelations(): HasMany
    {
        return $this->hasMany(LocationTypeRelation::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }
}
