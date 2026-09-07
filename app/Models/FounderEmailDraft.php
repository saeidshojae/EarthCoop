<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FounderEmailDraft extends Model
{
    protected $guarded = [];

    protected $casts = [
        'recipients' => 'array',
        'variables' => 'array',
        'sent_at' => 'datetime',
    ];
}
