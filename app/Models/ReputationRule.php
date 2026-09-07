<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReputationRule extends Model
{
    protected $table = 'reputation_rules';

    protected $fillable = [
        'key', 'label', 'weight', 'description', 'module', 'active', 'daily_cap',
        'dimension', 'convertible', 'repeat_policy',
    ];

    protected $casts = [
        'active' => 'boolean',
        'convertible' => 'boolean',
        'weight' => 'integer',
        'daily_cap' => 'integer',
    ];
}
