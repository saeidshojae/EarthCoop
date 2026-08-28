<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModerationCaseSummary extends Model
{
    protected $fillable = [
        'source_type','source_id','classification','severity','summary','status','reason_code',
    ];
}
