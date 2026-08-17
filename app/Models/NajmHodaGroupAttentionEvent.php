<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NajmHodaGroupAttentionEvent extends Model
{
    protected $fillable = [
        'group_id','action_item_id','event_type','fingerprint','occurrences',
        'first_seen_at','last_seen_at','last_notified_at','resolved_at','payload',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_notified_at' => 'datetime',
        'resolved_at' => 'datetime',
        'payload' => 'array',
    ];

    public function group(): BelongsTo { return $this->belongsTo(Group::class); }
    public function actionItem(): BelongsTo { return $this->belongsTo(NajmHodaGroupActionItem::class, 'action_item_id'); }
}
