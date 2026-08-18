<?php

namespace App\Modules\NajmBahar\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipRetirement extends Model
{
    protected $table = 'najm_bahar_membership_retirements';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'retired_at' => 'datetime',
    ];

    public function liability()
    {
        return $this->hasOne(MonetaryRetirementLiability::class, 'retirement_id');
    }
}
