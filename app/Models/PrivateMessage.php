<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PrivateMessage extends Model
{
    protected $fillable = [
        'private_conversation_id',
        'sender_id',
        'message',
    ];

    /**
     * Casts
     */
    protected $casts = [
        'is_pinned' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(PrivateConversation::class, 'private_conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Reactions on this message (polymorphic)
     */
    public function reactions(): MorphMany
    {
        return $this->morphMany(MessageReaction::class, 'message');
    }

    /**
     * Get reaction summary: ['👍' => 2, '❤️' => 1]
     */
    public function getReactionSummaryAttribute(): array
    {
        return $this->reactions()
            ->selectRaw('reaction_type, COUNT(*) as count')
            ->groupBy('reaction_type')
            ->pluck('count', 'reaction_type')
            ->toArray();
    }

    /**
     * Get users who reacted with a specific type
     */
    public function getUsersWithReaction($reactionType): array
    {
        return $this->reactions()
            ->withReaction($reactionType)
            ->with('user:id,full_name')
            ->get()
            ->pluck('user.full_name')
            ->toArray();
    }

    /**
     * Check if current user has a specific reaction
     */
    public function hasUserReacted(string $userId, string $reactionType): bool
    {
        return $this->reactions()
            ->where('user_id', $userId)
            ->where('reaction_type', $reactionType)
            ->exists();
    }
}
