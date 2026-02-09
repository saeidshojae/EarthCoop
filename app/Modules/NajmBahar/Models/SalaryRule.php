<?php

namespace App\Modules\NajmBahar\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryRule extends Model
{
    protected $table = 'najm_bahar_salary_rules';

    protected $fillable = [
        'name',
        'rule_type',
        'group_id',
        'user_id',
        'role_code',
        'project_id',
        'amount_gol',
        'schedule_type',
        'interval_days',
        'start_at',
        'end_at',
        'min_activity_score',
        'requires_senior_approval',
        'is_active',
        'last_run_at',
        'meta',
    ];

    protected $casts = [
        'amount_gol' => 'integer',
        'interval_days' => 'integer',
        'min_activity_score' => 'integer',
        'requires_senior_approval' => 'boolean',
        'is_active' => 'boolean',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'last_run_at' => 'datetime',
        'meta' => 'array',
    ];
}
