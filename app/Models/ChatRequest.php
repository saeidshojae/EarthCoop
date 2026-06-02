<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatRequest extends Model
{
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'status',
        'message',
        'group_id',
        'request_to_group',
        'private_conversation_id',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function privateConversation(): BelongsTo
    {
        return $this->belongsTo(PrivateConversation::class, 'private_conversation_id');
    }
} 
