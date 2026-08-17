<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NajmHodaGroupAttentionSetting extends Model
{
    protected $fillable = [
        'group_id','enabled','due_soon_hours','suppress_minutes',
        'alert_overdue','alert_due_soon','alert_blocked','alert_urgent','alert_unassigned',
        'digest_mode','timezone','preferred_time','last_evaluated_at','last_digest_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'alert_overdue' => 'boolean',
        'alert_due_soon' => 'boolean',
        'alert_blocked' => 'boolean',
        'alert_urgent' => 'boolean',
        'alert_unassigned' => 'boolean',
        'last_evaluated_at' => 'datetime',
        'last_digest_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
