<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NajmHodaGroupActionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'source_message_id',
        'response_message_id',
        'assigned_user_id',
        'title',
        'details',
        'assignee_name',
        'due_at',
        'due_text',
        'priority',
        'status',
        'meta',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'meta' => 'array',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function sourceMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'source_message_id');
    }

    public function responseMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'response_message_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
