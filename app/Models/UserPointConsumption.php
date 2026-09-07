<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPointConsumption extends Model
{
    protected $fillable = [
        'user_id',
        'user_point_conversion_id',
        'user_point_transaction_id',
        'points_consumed',
        'conversion_key',
        'policy_version_id',
        'policy_version',
    ];

    public function conversion()
    {
        return $this->belongsTo(UserPointConversion::class, 'user_point_conversion_id');
    }
}
