<?php

namespace App\Modules\Governance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PublicExecutionAuthorization extends Model
{
    protected $table = 'governance_public_execution_authorizations';

    protected $fillable = [
        'plan_id', 'authorized_by', 'status', 'conditions', 'authorized_at',
    ];

    protected $casts = [
        'conditions' => 'array',
        'authorized_at' => 'datetime',
    ];

    public function plan() { return $this->belongsTo(PublicContributionPlan::class, 'plan_id'); }
    public function authorizedBy() { return $this->belongsTo(User::class, 'authorized_by'); }
}
