<?php

namespace App\Modules\NajmBahar\Models;

use Illuminate\Database\Eloquent\Model;

class MonetaryRetirementLiability extends Model
{
    protected $table = 'najm_bahar_monetary_retirement_liabilities';

    protected $guarded = [];

    protected $casts = ['metadata' => 'array'];

    public function retirement()
    {
        return $this->belongsTo(MembershipRetirement::class, 'retirement_id');
    }
}
