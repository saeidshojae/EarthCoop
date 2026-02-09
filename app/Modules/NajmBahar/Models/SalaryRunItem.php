<?php

namespace App\Modules\NajmBahar\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryRunItem extends Model
{
    protected $table = 'najm_bahar_salary_run_items';

    protected $fillable = [
        'run_id',
        'rule_id',
        'group_id',
        'user_id',
        'role_code',
        'project_id',
        'period_start',
        'period_end',
        'amount_gol',
        'activity_score',
        'activity_threshold',
        'requires_senior_approval',
        'senior_approved_at',
        'senior_approved_by',
        'status',
        'blocked_reason',
        'transaction_id',
    ];

    protected $casts = [
        'amount_gol' => 'integer',
        'activity_score' => 'integer',
        'activity_threshold' => 'integer',
        'requires_senior_approval' => 'boolean',
        'period_start' => 'date',
        'period_end' => 'date',
        'senior_approved_at' => 'datetime',
    ];
}
