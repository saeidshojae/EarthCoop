<?php

namespace App\Modules\NajmBahar\Models;

use Illuminate\Database\Eloquent\Model;

class MonetaryPolicyVersion extends Model
{
    protected $table = 'najm_bahar_monetary_policy_versions';

    protected $fillable = [
        'version',
        'status',
        'parameters',
        'reason',
        'effective_from',
        'effective_until',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'parameters' => 'array',
        'effective_from' => 'datetime',
        'effective_until' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function scopeEffective($query, $at = null)
    {
        $at = $at ?: now();

        return $query
            ->where('status', 'active')
            ->where(function ($q) use ($at) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $at);
            })
            ->where(function ($q) use ($at) {
                $q->whereNull('effective_until')->orWhere('effective_until', '>', $at);
            });
    }
}
