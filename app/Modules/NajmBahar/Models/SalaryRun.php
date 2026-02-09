<?php

namespace App\Modules\NajmBahar\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryRun extends Model
{
    protected $table = 'najm_bahar_salary_runs';

    protected $fillable = [
        'run_date',
        'period_start',
        'period_end',
        'status',
        'created_by',
        'meta',
    ];

    protected $casts = [
        'run_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'meta' => 'array',
    ];
}
