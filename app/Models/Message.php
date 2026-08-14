<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id', 
        'user_id', 
        'message', 
        'parent_id',
        'thread_id',
        'reply_count',
        'file_path',
        'file_type',
        'file_name',
        'voice_message',
        'client_message_id',
        'edited', 
        'edited_by',
        'edited_at',
        'lifecycle_state',
        'delivered_at',
        'deleted_at',
        'deleted_by',
        'removed_by',
        'read_by'
    ];

    protected $casts = [
        'edited' => 'boolean',
        'read_by' => 'array',
        'edited_at' => 'datetime',
        'delivered_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /** Exclude lifecycle tombstones from every user-visible chat feed. */
    public function scopeVisibleInChat(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNull('lifecycle_state')
                ->orWhere('lifecycle_state', '!=', 'deleted');
        });
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(Message::class, 'parent_id');
    }

    /**
     * Root message of the thread
     */
    public function thread()
    {
        return $this->belongsTo(Message::class, 'thread_id');
    }

    /**
     * Replies in this thread
     */
    public function replies()
    {
        return $this->hasMany(Message::class, 'thread_id')->orderBy('created_at', 'asc');
    }

    /**
     * Increment reply count
     */
    public function incrementReplyCount(): void
    {
        $this->increment('reply_count');
    }

    /**
     * Decrement reply count
     */
    public function decrementReplyCount(): void
    {
        $this->decrement('reply_count');
    }

    /**
     * Mark message as read by user
     */
    public function markAsRead(int $userId): void
    {
        $readBy = $this->read_by ?? [];
        $readBy[$userId] = now()->toIso8601String();
        $this->update(['read_by' => $readBy]);
    }

    /**
     * Check if message is read by user
     */
    public function isReadBy(int $userId): bool
    {
        $readBy = $this->read_by ?? [];
        return isset($readBy[$userId]);
    }

    /**
     * Get read count
     */
    public function getReadCountAttribute(): int
    {
        return count($this->read_by ?? []);
    }

    /**
     * Message reactions
     */
    public function reactions()
    {
        return $this->morphMany(MessageReaction::class, 'message');
    }

    /**
     * Get reactions grouped by type
     */
    public function getReactionsByTypeAttribute(): array
    {
        return $this->reactions()
            ->selectRaw('reaction_type, COUNT(*) as count')
            ->groupBy('reaction_type')
            ->pluck('count', 'reaction_type')
            ->toArray();
    }
}
