<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPointConversion extends Model
{
    protected $fillable = [
        'user_id',
        'request_key',
        'conversion_key',
        'requested_points',
        'consumed_points',
        'amount_gol',
        'ratio',
        'policy_version_id',
        'policy_version',
        'status',
    ];

    protected $casts = [
        'requested_points' => 'integer',
        'consumed_points' => 'integer',
        'amount_gol' => 'integer',
        'ratio' => 'integer',
        'policy_version_id' => 'integer',
    ];

    public function consumptions()
    {
        return $this->hasMany(UserPointConsumption::class, 'user_point_conversion_id');
    }
}
