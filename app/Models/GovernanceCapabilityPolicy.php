<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GovernanceCapabilityPolicy extends Model
{
    protected $fillable = [
        'scope',
        'country_code',
        'governance_type',
        'capabilities',
    ];

    protected $casts = [
        'capabilities' => 'array',
    ];
}
