<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'support_chat_id',
        'user_id',
        'type',
        'message',
        'attachments',
        'read_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'read_at' => 'datetime',
    ];

    public function supportChat(): BelongsTo
    {
        return $this->belongsTo(SupportChat::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function markAsRead(): void
    {
        if (!$this->isRead()) {
            $this->update(['read_at' => now()]);
        }
    }

    public function isFromUser(): bool
    {
        return $this->type === 'user';
    }

    public function isFromAgent(): bool
    {
        return $this->type === 'agent';
    }

    public function isSystem(): bool
    {
        return $this->type === 'system';
    }
}
