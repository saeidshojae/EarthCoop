<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NajmHodaRuntimeEvent extends Model
{
    use HasFactory;

    protected $table = 'najm_hoda_runtime_events';

    protected $fillable = [
        'event',
        'request_id',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

