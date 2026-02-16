<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NajmHodaGroupConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'enabled',
        'assistant_role',
        'default_agent',
        'auto_reply_mode',
        'knowledge_scope',
        'meeting_mode_enabled',
        'allow_proactive_guidance',
        'max_replies_per_hour',
        'min_reply_interval_seconds',
        'policy',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'meeting_mode_enabled' => 'boolean',
        'allow_proactive_guidance' => 'boolean',
        'max_replies_per_hour' => 'integer',
        'min_reply_interval_seconds' => 'integer',
        'policy' => 'array',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
