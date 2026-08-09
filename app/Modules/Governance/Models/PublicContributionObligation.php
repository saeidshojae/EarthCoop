<?php

namespace App\Modules\Governance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PublicContributionObligation extends Model
{
    protected $table = 'governance_public_contribution_obligations';

    protected $fillable = [
        'plan_id', 'user_id', 'amount_gol', 'paid_gol', 'status',
        'due_at', 'paid_at', 'metadata',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function plan() { return $this->belongsTo(PublicContributionPlan::class, 'plan_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
