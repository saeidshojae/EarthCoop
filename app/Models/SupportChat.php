<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportChat extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'agent_id',
        'ticket_id',
        'status',
        'priority',
        'subject',
        'resolved_at',
        'last_activity_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportChatMessage::class)->orderBy('created_at', 'asc');
    }

    public function unreadMessagesCount(): int
    {
        return $this->messages()
            ->where('type', '!=', 'system')
            ->where('user_id', '!=', auth()->id())
            ->whereNull('read_at')
            ->count();
    }

    public function markAsRead(): void
    {
        $this->messages()
            ->where('user_id', '!=', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isWaiting(): bool
    {
        return $this->status === 'waiting';
    }

    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }
}
