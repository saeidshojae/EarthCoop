<?php

namespace App\Modules\NajmBahar\Models;

use Illuminate\Database\Eloquent\Model;

class IdleMoneyAssessment extends Model
{
    protected $table = 'najm_bahar_idle_money_assessments';

    protected $guarded = [];

    protected $casts = [
        'as_of' => 'datetime',
        'last_external_active_outflow_at' => 'datetime',
        'idle_since' => 'datetime',
        'metadata' => 'array',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function policyVersion()
    {
        return $this->belongsTo(MonetaryPolicyVersion::class, 'policy_version_id');
    }
}
