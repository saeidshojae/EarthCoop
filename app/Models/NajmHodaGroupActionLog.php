<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NajmHodaGroupActionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'trigger_message_id',
        'response_message_id',
        'action_type',
        'decision',
        'agent',
        'reason',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function triggerMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'trigger_message_id');
    }

    public function responseMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'response_message_id');
    }
}
