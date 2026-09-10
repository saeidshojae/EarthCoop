<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipDimension extends Model
{
    protected $fillable = [
        'key',
        'name',
        'resolver_class',
        'enabled',
        'metadata',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'metadata' => 'array',
    ];

    public function creationPolicies(): HasMany
    {
        return $this->hasMany(GroupCreationPolicy::class);
    }
}
