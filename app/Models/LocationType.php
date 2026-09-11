<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LocationType extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'canonical_name', 'is_residence_endpoint', 'metadata'];

    protected $casts = [
        'is_residence_endpoint' => 'boolean',
        'metadata' => 'array',
    ];

    public function schemas(): BelongsToMany
    {
        return $this->belongsToMany(LocationSchema::class, 'location_schema_types')
            ->withPivot(['is_root', 'is_residence_endpoint', 'sort_order', 'metadata'])
            ->withTimestamps();
    }

    public function parentRelations(): HasMany
    {
        return $this->hasMany(LocationTypeRelation::class, 'parent_type_id');
    }

    public function childRelations(): HasMany
    {
        return $this->hasMany(LocationTypeRelation::class, 'child_type_id');
    }
}
