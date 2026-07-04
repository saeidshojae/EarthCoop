<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageReaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'message_type',
        'user_id',
        'reaction_type'
    ];

    /**
     * Reaction types available
     */
    const REACTIONS = ['👍', '❤️', '😂', '😮', '😢', '🔥', '👎'];

    /**
     * Get the parent message (polymorphic)
     */
    public function message()
    {
        return $this->morphTo();
    }

    /**
     * The user who reacted
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all users with a specific reaction
     */
    public function scopeWithReaction($query, $reactionType)
    {
        return $query->where('reaction_type', $reactionType);
    }
}