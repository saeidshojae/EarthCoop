<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupChatOutbox extends Model
{
    protected $table = 'group_chat_outbox';
    protected $fillable = ['event_id', 'group_id', 'feed_item_id', 'sequence', 'type', 'actor_id', 'payload', 'status', 'attempts', 'available_at', 'published_at', 'last_error'];
    protected $casts = ['payload' => 'array', 'available_at' => 'datetime', 'published_at' => 'datetime'];
}
