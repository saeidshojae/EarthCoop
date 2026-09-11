<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GovernanceArea extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'key',
        'country_code',
        'governance_type',
        'area_kind',
        'canonical_name',
        'localized_names',
        'rank',
        'status',
        'metadata',
    ];

    protected $casts = [
        'localized_names' => 'array',
        'metadata' => 'array',
        'rank' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'governance_area_locations')->withTimestamps();
    }

    public function override(): HasOne
    {
        return $this->hasOne(GovernanceAreaOverride::class);
    }

    public function scopeOfficial(Builder $query): Builder
    {
        return $query->where('area_kind', 'official');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
