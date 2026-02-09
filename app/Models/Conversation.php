<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'agent_type',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * رابطه با کاربر
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * رابطه با پیام‌ها
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    /**
     * دریافت آخرین پیام
     */
    public function lastMessage()
    {
        return $this->hasOne(ConversationMessage::class)->latestOfMany();
    }

    /**
     * Scope برای مکالمات فعال
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope برای مکالمات یک عامل خاص
     */
    public function scopeByAgent($query, string $agentType)
    {
        return $query->where('agent_type', $agentType);
    }
}
