<?php

namespace App\Models;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ConversationMessage $message): void {
            if ((string) $message->role !== 'user' || !auth()->check()) {
                return;
            }

            $conversation = Conversation::query()->find($message->conversation_id);
            $actorId = auth()->id();

            if (!$conversation || (int) $conversation->user_id !== (int) $actorId) {
                throw new AuthorizationException('You are not authorized to write to this conversation.');
            }
        });
    }

    /**
     * رابطه با مکالمه
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
