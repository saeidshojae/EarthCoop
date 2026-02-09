<?php

namespace App\Modules\NajmBahar\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledTransaction extends Model
{
    protected $table = 'najm_scheduled_transactions';
    protected $fillable = [
        'transaction_id',
        'execute_at',
        'status',
        'attempts',
        'payload'
    ];

    protected $casts = [
        'execute_at' => 'datetime',
        'payload' => 'array',
    ];

    public $timestamps = true;
}
