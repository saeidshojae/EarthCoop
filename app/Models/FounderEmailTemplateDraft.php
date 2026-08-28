<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FounderEmailTemplateDraft extends Model
{
    protected $guarded = [];

    protected $casts = [
        'changes' => 'array',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function template()
    {
        return $this->belongsTo(EmailTemplate::class, 'template_id');
    }
}
