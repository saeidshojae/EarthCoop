<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivateMessage extends Model
{
    protected $fillable = [
        'private_conversation_id',
        'sender_id',
        'message',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(PrivateConversation::class, 'private_conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
